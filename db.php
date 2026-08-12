<?php
// db.php
$host = 'localhost'; // DirectAdmin genelde localhost kullanır
$db   = 'anatoliaorganik_anketdb'; // DirectAdmin panelinden açacağın DB adı
$user = 'anatoliaorganik_anketdbmirac'; // DB Kullanıcı adı
$pass = 'A89UWWzpwnsgJGW6d5vZ'; // DB Şifresi
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Tabloları Oluşturma (main.py'deki init_db karşılığı)
$pdo->exec("CREATE TABLE IF NOT EXISTS Questions (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    question_text TEXT, 
    question_type VARCHAR(50), 
    is_active TINYINT(1) DEFAULT 1, 
    is_required TINYINT(1) DEFAULT 1
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS SurveySessions (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS Answers (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    session_id INT, 
    question_id INT, 
    answer_value TEXT,
    FOREIGN KEY(session_id) REFERENCES SurveySessions(id), 
    FOREIGN KEY(question_id) REFERENCES Questions(id)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS Settings (
    setting_key VARCHAR(50) PRIMARY KEY, 
    setting_value TEXT
)");

// Varsayılan Logo Ayarı
$pdo->exec("INSERT IGNORE INTO Settings (setting_key, setting_value) VALUES ('logo_url', '3N.png')");

// Varsayılan Soruları Ekleme (Eğer soru yoksa)
$stmt = $pdo->query("SELECT COUNT(*) FROM Questions");
if ($stmt->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO Questions (question_text, question_type, is_required) VALUES ('Hizmetimizi Nasıl Buldunuz?', 'rating', 1)");
    $pdo->exec("INSERT INTO Questions (question_text, question_type, is_required) VALUES ('Eklemek istediğiniz önerileriniz nelerdir?', 'text', 0)");
}
?>