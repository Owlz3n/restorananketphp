<?php
// api.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db.php';
require_once 'Filter.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ROTA: /api.php?action=questions (Müşteriye aktif soruları getirir)
if ($method == 'GET' && $action == 'questions') {
    $stmt = $pdo->query("SELECT id, question_text, question_type, is_required FROM Questions WHERE is_active = 1");
    $questions = $stmt->fetchAll();
    echo json_encode($questions);
    exit;
}

// ROTA: /api.php?action=submit (Anketi veritabanına kaydeder)
if ($method == 'POST' && $action == 'submit') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['answers'])) {
        http_response_code(400);
        echo json_encode(["detail" => "Anket doldurulamadı. Lütfen en az bir soruyu cevaplayınız."]);
        exit;
    }

    // Zorunlu soruları kontrol et
    $stmt = $pdo->query("SELECT id, question_text FROM Questions WHERE is_active = 1 AND is_required = 1");
    $req_questions = $stmt->fetchAll();
    
    $answered_ids = [];
    foreach ($data['answers'] as $ans) {
        if (trim($ans['answer_value']) !== "") {
            $answered_ids[] = (int)$ans['question_id'];
        }
    }

    foreach ($req_questions as $req_q) {
        if (!in_array($req_q['id'], $answered_ids)) {
            http_response_code(400);
            echo json_encode(["detail" => "Lütfen tüm zorunlu soruları doldurunuz! ('" . $req_q['question_text'] . "' boş bırakılamaz)"]);
            exit;
        }
    }

    // Küfür filtresi kontrolü
    $filter = new Filter();
    foreach ($data['answers'] as $ans) {
        if ($filter->has_bad_word($ans['answer_value'])) {
            http_response_code(400);
            echo json_encode(["detail" => "Anketiniz gönderilmedi! Yanıtınızda uygunsuz veya küfürlü ifadeler tespit edildi. Lütfen küfürlü ifadeler kullanmayınız."]);
            exit;
        }
    }

    // Cevapları Veritabanına Yazma İşlemi
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO SurveySessions (created_at) VALUES (CURRENT_TIMESTAMP)");
        $stmt->execute();
        $session_id = $pdo->lastInsertId();

        $stmtAns = $pdo->prepare("INSERT INTO Answers (session_id, question_id, answer_value) VALUES (?, ?, ?)");
        
        foreach ($data['answers'] as $ans) {
            if (trim($ans['answer_value']) !== "") {
                $stmtAns->execute([$session_id, $ans['question_id'], (string)$ans['answer_value']]);
            }
        }
        
        $pdo->commit();
        echo json_encode(["message" => "Anket başarıyla kaydedildi!"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(["detail" => $e->getMessage()]);
    }
    exit;
}

http_response_code(404);
echo json_encode(["detail" => "Rota bulunamadı."]);
?>