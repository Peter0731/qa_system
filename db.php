<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit; // CORS 預檢

$dsn = 'mysql:host=localhost;dbname=qa_system;charset=utf8mb4';
$user = '';     // 資料庫帳號
$pass = '';   // 資料庫密碼

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>'資料庫連線失敗']);
    exit;
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $q = $pdo->query("SELECT * FROM cards ORDER BY status, sort_order, id ASC");
        $cards = $q->fetchAll(PDO::FETCH_ASSOC);

        // 將 fixed 欄位轉成布林
        foreach ($cards as &$card) {
            $card['fixed'] = $card['fixed'] ? true : false;
        }
        echo json_encode($cards, JSON_UNESCAPED_UNICODE);
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO cards (section, description, status, date, sort_order, fixed) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['section'] ?? '',
            $data['description'] ?? '',
            $data['status'] ?? 'not-started',
            $data['date'] ?? null,
            0,
            !empty($data['fixed']) ? 1 : 0
        ]);
        $data['id'] = $pdo->lastInsertId();
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['fixed'] = !empty($data['fixed']) ? true : false;
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("UPDATE cards SET section=?, description=?, status=?, date=?, fixed=? WHERE id=?");
        $stmt->execute([
            $data['section'] ?? '',
            $data['description'] ?? '',
            $data['status'] ?? 'not-started',
            $data['date'] ?? null,
            !empty($data['fixed']) ? 1 : 0,
            $data['id']
        ]);
        echo json_encode(['success' => true]);
        break;
    case 'PATCH':
        // 拖曳排序
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['order']) && isset($data['status'])) {
            foreach ($data['order'] as $sort => $id) {
                $stmt = $pdo->prepare("UPDATE cards SET status=?, sort_order=? WHERE id=?");
                $stmt->execute([
                    $data['status'],
                    $sort,
                    $id
                ]);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error'=>'缺少參數']);
        }
        break;
    case 'DELETE':
        $id = $_GET['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM cards WHERE id=?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error'=>'Method not allowed']);
        break;
}