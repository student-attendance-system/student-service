<?php
require __DIR__ . '/../../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Get JWT secret reliably
$jwtSecret = getenv('JWT_SECRET');
if (!$jwtSecret) {
    die(json_encode(['error' => 'JWT_SECRET environment variable not set']));
}

// Database connection
function getDB() {
    $host = getenv('DB_HOST');
    $db   = getenv('MYSQL_NAME');
    $user = getenv('MYSQL_USER');
    $pass = getenv('MYSQL_PASSWORD');
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;port=3306;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}

// Create App
$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$app->add(new \Tuupola\Middleware\CorsMiddleware([
    "origin" => ["*"],
    "methods" => ["GET","POST","PUT","PATCH","DELETE"],
    "headers.allow" => ["Authorization","Content-Type"],
    "credentials" => true,
    "cache" => 0,
]));

// JWT Middleware
$jwtMiddleware = function(Request $request, RequestHandler $handler) use ($jwtSecret): Response {
    $authHeader = $request->getHeaderLine('Authorization');
    $response = new Slim\Psr7\Response();

    if (!$authHeader) {
        $response->getBody()->write(json_encode(['error' => 'Authorization header required']));
        return $response->withStatus(401)->withHeader('Content-Type','application/json');
    }

    $token = str_replace('Bearer ','',$authHeader);

    try {
        $decoded = JWT::decode($token, new Key($jwtSecret,'HS256'));
        $request = $request->withAttribute('user', $decoded);
        return $handler->handle($request);
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(['error'=>'Invalid token']));
        return $response->withStatus(401)->withHeader('Content-Type','application/json');
    }
};

// --- Login Route ---
$app->post('/login', function(Request $request, Response $response) use ($jwtSecret) {
    $data = $request->getParsedBody() ?? [];
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $role = strtolower(trim($data['role'] ?? ''));

    if (!$username || !$password || !$role) {
        $response->getBody()->write(json_encode(['error'=>'Missing credentials']));
        return $response->withHeader('Content-Type','application/json')->withStatus(400);
    }

    $tableMap = [
        'admin' => ['table'=>'tbladmin','column'=>'emailAddress'],
        'teacher' => ['table'=>'tblclassteacher','column'=>'emailAddress'],
        'student' => ['table'=>'tblstudents','column'=>'admissionNumber']
    ];

    if (!isset($tableMap[$role])) {
        $response->getBody()->write(json_encode(['error'=>'Invalid role']));
        return $response->withHeader('Content-Type','application/json')->withStatus(400);
    }

    try {
        $db = getDB();
        $table = $tableMap[$role]['table'];
        $column = $tableMap[$role]['column'];
        $stmt = $db->prepare("SELECT * FROM $table WHERE $column=? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password,$user['password'])) {
            $response->getBody()->write(json_encode(['error'=>'Invalid credentials']));
            return $response->withHeader('Content-Type','application/json')->withStatus(401);
        }

        $payload = [
            'sub' => $user['Id'],
            'role' => $role,
            'iat' => time(),
            'exp' => time()+3600
        ];

        $token = JWT::encode($payload, $jwtSecret,'HS256');

        $response->getBody()->write(json_encode(['token'=>$token]));
        return $response->withHeader('Content-Type','application/json')->withStatus(200);

    } catch (Exception $e) {
        $response->getBody()->write(json_encode(['error'=>'Server error','details'=>$e->getMessage()]));
        return $response->withHeader('Content-Type','application/json')->withStatus(500);
    }
});

// --- Protected Routes ---
$app->get('/students', function(Request $request, Response $response) {
    $db = getDB();
    $stmt = $db->query("SELECT id, name, email FROM tblstudents");
    $response->getBody()->write(json_encode($stmt->fetchAll()));
    return $response->withHeader('Content-Type','application/json');
})->add($jwtMiddleware);

$app->get('/attendance', function(Request $request, Response $response) {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM attendance");
    $response->getBody()->write(json_encode($stmt->fetchAll()));
    return $response->withHeader('Content-Type','application/json');
})->add($jwtMiddleware);

// Health check
$app->get('/health', function(Request $request, Response $response) {
    $response->getBody()->write(json_encode(['status'=>'ok']));
    return $response->withHeader('Content-Type','application/json')->withStatus(200);
});

$app->run();

