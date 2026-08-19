<?php
require_once __DIR__ . '/config.php';

/** Зажать рейтинг в 1..5. */
function review_clamp_rating(int $r): int {
    return max(1, min(5, $r));
}

/** Строка звёзд длиной 5: заполненные ★, остальные ☆. */
function review_stars(int $r): string {
    $n = review_clamp_rating($r);
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}

/** Чистая валидация отзыва. Возвращает ok/data/errors, БД не трогает. */
function review_validate(array $in): array {
    $errors = [];
    $name = trim((string)($in['author_name'] ?? ''));
    $body = trim((string)($in['body'] ?? ''));
    $role = trim((string)($in['author_role'] ?? ''));
    $rating = review_clamp_rating((int)($in['rating'] ?? 5));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['author_name'] = 'Укажите имя (2–100 символов)';
    }
    if (mb_strlen($body) < 10 || mb_strlen($body) > 2000) {
        $errors['body'] = 'Текст отзыва — от 10 до 2000 символов';
    }
    return [
        'ok' => empty($errors),
        'data' => ['author_name' => $name, 'author_role' => $role !== '' ? $role : null, 'body' => $body, 'rating' => $rating],
        'errors' => $errors,
    ];
}

/** Одобренные отзывы (общие или по товару), новые сверху. */
function reviews_approved(int $limit = 6, ?int $productId = null): array {
    try {
        $db = getDbConnection();
        if ($productId !== null) {
            $stmt = $db->prepare("SELECT * FROM reviews WHERE is_approved=1 AND product_id=? ORDER BY created_at DESC, id DESC LIMIT " . (int)$limit);
            $stmt->execute([$productId]);
        } else {
            $stmt = $db->query("SELECT * FROM reviews WHERE is_approved=1 ORDER BY created_at DESC, id DESC LIMIT " . (int)$limit);
        }
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) { error_log('reviews_approved: ' . $e->getMessage()); return []; }
}

/** Добавить отзыв (на модерацию, is_approved=0). $data — из review_validate()['data']. */
function review_add(array $data): bool {
    try {
        $stmt = getDbConnection()->prepare(
            "INSERT INTO reviews (author_name, author_role, rating, body, product_id, is_approved)
             VALUES (:name, :role, :rating, :body, :pid, 0)"
        );
        return $stmt->execute([
            ':name' => $data['author_name'], ':role' => $data['author_role'] ?? null,
            ':rating' => review_clamp_rating((int)($data['rating'] ?? 5)),
            ':body' => $data['body'], ':pid' => $data['product_id'] ?? null,
        ]);
    } catch (PDOException $e) { error_log('review_add: ' . $e->getMessage()); return false; }
}

/** Все отзывы для админки (новые сверху). */
function reviews_all(): array {
    try { return getDbConnection()->query("SELECT * FROM reviews ORDER BY is_approved ASC, created_at DESC, id DESC")->fetchAll() ?: []; }
    catch (PDOException $e) { error_log('reviews_all: ' . $e->getMessage()); return []; }
}

function review_set_approved(int $id, bool $ok): void {
    try { $s = getDbConnection()->prepare("UPDATE reviews SET is_approved=? WHERE id=?"); $s->execute([$ok ? 1 : 0, $id]); }
    catch (PDOException $e) { error_log('review_set_approved: ' . $e->getMessage()); }
}

function review_delete(int $id): void {
    try { $s = getDbConnection()->prepare("DELETE FROM reviews WHERE id=?"); $s->execute([$id]); }
    catch (PDOException $e) { error_log('review_delete: ' . $e->getMessage()); }
}
