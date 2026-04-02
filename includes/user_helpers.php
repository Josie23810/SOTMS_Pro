<?php
require_once __DIR__ . '/../config/db.php';

function getStudentRecord(PDO $pdo, $userId) {
    $stmt = $pdo->prepare('SELECT id, user_id FROM students WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        return $student;
    }

    $stmt = $pdo->prepare('INSERT INTO students (user_id) VALUES (?)');
    $stmt->execute([$userId]);
    return ['id' => $pdo->lastInsertId(), 'user_id' => $userId];
}

function getTutorRecord(PDO $pdo, $userId) {
    $stmt = $pdo->prepare('SELECT id, user_id FROM tutors WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $tutor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tutor) {
        return $tutor;
    }

    $stmt = $pdo->prepare('INSERT INTO tutors (user_id) VALUES (?)');
    $stmt->execute([$userId]);
    return ['id' => $pdo->lastInsertId(), 'user_id' => $userId];
}

function getStudentId(PDO $pdo, $userId) {
    $student = getStudentRecord($pdo, $userId);
    return $student['id'] ?? null;
}

function getTutorId(PDO $pdo, $userId) {
    $tutor = getTutorRecord($pdo, $userId);
    return $tutor['id'] ?? null;
}

function getAvailableTutors(PDO $pdo) {
    $stmt = $pdo->prepare('SELECT t.id, u.name FROM tutors t JOIN users u ON t.user_id = u.id ORDER BY u.name');
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ensureSessionStructure(PDO $pdo) {
    $required = [
        'subject' => 'VARCHAR(100) NULL',
        'duration' => 'INT DEFAULT 60',
        'notes' => 'TEXT NULL',
        'tutor_id' => 'INT NULL',
        'session_date' => 'DATETIME NOT NULL',
        'status' => "ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending'"
    ];

    foreach ($required as $column => $definition) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM sessions LIKE ?");
        $stmt->execute([$column]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE sessions ADD COLUMN $column $definition");
        }
    }
}

function fetchMessagesForUser(PDO $pdo, $userId, $type = 'received', $limit = 20) {
    if ($type === 'received') {
        $sql = 'SELECT m.*, u.name AS sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = ? ORDER BY m.created_at DESC LIMIT ?';
    } else {
        $sql = 'SELECT m.*, u.name AS receiver_name FROM messages m JOIN users u ON m.receiver_id = u.id WHERE m.sender_id = ? ORDER BY m.created_at DESC LIMIT ?';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markMessagesRead(PDO $pdo, $userId) {
    $stmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
}
