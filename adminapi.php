<?php
// admin_api.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// 1. Tüm Soruları Getir
if ($method == 'GET' && $action == 'questions') {
    $stmt = $pdo->query("SELECT * FROM Questions ORDER BY id DESC");
    echo json_encode($stmt->fetchAll());
    exit;
}

// 2. Yeni Soru Ekle
if ($method == 'POST' && $action == 'add_question') {
    $data = json_decode(file_get_contents("php://input"), true);
    $is_req = (isset($data['is_required']) && $data['is_required'] !== null) ? (int)$data['is_required'] : 1;
    
    $stmt = $pdo->prepare("INSERT INTO Questions (question_text, question_type, is_required) VALUES (?, ?, ?)");
    $stmt->execute([$data['question_text'], $data['question_type'], $is_req]);
    echo json_encode(["message" => "Soru başarıyla eklendi!"]);
    exit;
}

// 3. Soru Durumunu Değiştir (Aktif/Pasif)
if ($method == 'PUT' && $action == 'toggle' && isset($_GET['q_id'])) {
    $stmt = $pdo->prepare("UPDATE Questions SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?");
    $stmt->execute([$_GET['q_id']]);
    echo json_encode(["message" => "Soru durumu güncellendi!"]);
    exit;
}

// 4. Soru Zorunluluk Durumunu Değiştir
if ($method == 'PUT' && $action == 'toggle-required' && isset($_GET['q_id'])) {
    $stmt = $pdo->prepare("UPDATE Questions SET is_required = CASE WHEN is_required = 1 THEN 0 ELSE 1 END WHERE id = ?");
    $stmt->execute([$_GET['q_id']]);
    echo json_encode(["message" => "Soru zorunluluk durumu güncellendi!"]);
    exit;
}

// 5. Soruyu Sil
if ($method == 'DELETE' && $action == 'delete_question' && isset($_GET['q_id'])) {
    $q_id = $_GET['q_id'];
    $pdo->prepare("DELETE FROM Answers WHERE question_id = ?")->execute([$q_id]);
    $pdo->prepare("DELETE FROM Questions WHERE id = ?")->execute([$q_id]);
    echo json_encode(["message" => "Soru ve bağlı tüm cevaplar silindi!"]);
    exit;
}

// 6. Anket Sonuçlarını Getir
if ($method == 'GET' && $action == 'results') {
    $query = "SELECT s.id as session_id, s.created_at, q.question_text, a.answer_value 
              FROM Answers a 
              JOIN SurveySessions s ON a.session_id = s.id 
              JOIN Questions q ON a.question_id = q.id";
    
    $params = [];
    if (!empty($_GET['date'])) {
        $query .= " WHERE DATE(s.created_at) = ?";
        $params[] = $_GET['date'];
    }
    
    $query .= " ORDER BY s.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    $grouped_results = [];
    foreach ($results as $r) {
        $s_id = $r['session_id'];
        if (!isset($grouped_results[$s_id])) {
            $grouped_results[$s_id] = ["tarih" => $r['created_at'], "cevaplar" => []];
        }
        $grouped_results[$s_id]['cevaplar'][] = ["soru" => $r['question_text'], "cevap" => $r['answer_value']];
    }
    echo json_encode($grouped_results);
    exit;
}

// 7. Logoyu Getir
if ($method == 'GET' && $action == 'get_logo') {
    $stmt = $pdo->query("SELECT setting_value FROM Settings WHERE setting_key = 'logo_url'");
    $row = $stmt->fetch();
    $logo_url = $row ? $row['setting_value'] : "3N.png";
    echo json_encode(["logo_url" => $logo_url]);
    exit;
}

// 8. Logoyu Güncelle (Dosya Yükleme)
if ($method == 'POST' && $action == 'update_logo') {
    $new_logo_path = null;
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true); // Klasör yoksa oluştur
        
        $filename = "logo_" . basename($_FILES['file']['name']);
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
            // Canlıya alırken "http://seninsiten.com/" olarak güncellemeyi unutma!
            $new_logo_path = "uploads/" . $filename; 
        }
    } elseif (isset($_POST['logo_url'])) {
        $new_logo_path = $_POST['logo_url'];
    }
    
    if ($new_logo_path) {
        $stmt = $pdo->prepare("REPLACE INTO Settings (setting_key, setting_value) VALUES ('logo_url', ?)");
        $stmt->execute([$new_logo_path]);
        echo json_encode(["message" => "Logo başarıyla güncellendi!", "logo_url" => $new_logo_path]);
    } else {
        http_response_code(400);
        echo json_encode(["detail" => "Lütfen bir dosya veya logo bağlantısı seçiniz."]);
    }
    exit;
}
// 9. Tekil Anket Sonucunu Sil
if ($method == 'DELETE' && $action == 'delete_session' && isset($_GET['session_id'])) {
    $stmt = $pdo->prepare("DELETE FROM Answers WHERE session_id = ?");
    $stmt->execute([$_GET['session_id']]);
    $stmt2 = $pdo->prepare("DELETE FROM SurveySessions WHERE id = ?");
    $stmt2->execute([$_GET['session_id']]);
    echo json_encode(["message" => "Anket oturumu silindi!"]);
    exit;
}

// 10. Tüm Anket Sonuçlarını Temizle
if ($method == 'DELETE' && $action == 'delete_all_sessions') {
    $pdo->query("DELETE FROM Answers");
    $pdo->query("DELETE FROM SurveySessions");
    echo json_encode(["message" => "Tüm anket sonuçları temizlendi!"]);
    exit;
}

?>