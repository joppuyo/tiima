<?php

require 'database.php';

try {
    $pdo->beginTransaction();

    $sth = $pdo->prepare('SELECT * FROM activity WHERE finished_at IS NULL LIMIT 1');
    $sth->execute();
    $currentActivity = $sth->fetch();

    if ($currentActivity !== false) {
        $sth = $pdo->prepare('UPDATE activity SET finished_at = CURRENT_TIMESTAMP WHERE id = ?');
        $sth->execute([$currentActivity['id']]);
    }

    $activity = [];
    $activity['title'] = $_POST['title'];
    $activity['tags'] = [];
    if (($tagStart = strpos($activity['title'], '#')) !== false) {
        $activity['tags'] = preg_split('/\s*#/', substr($activity['title'], $tagStart + 1));
        $activity['title'] = trim(substr($activity['title'], 0, $tagStart));
    }

    $sth = $pdo->prepare('INSERT INTO activity (title) VALUES (?) RETURNING id');
    $sth->execute([$activity['title']]);
    $activity['id'] = $sth->fetchColumn();

    if (count($activity['tags']) > 0) {
        $tags = [];
        foreach ($activity['tags'] as $tag) {
            $sth = $pdo->prepare('INSERT INTO tag (title) VALUES (?) ON CONFLICT DO NOTHING RETURNING id');
            $sth->execute([$tag]);
            $id = $sth->fetchColumn();
            if ($id === false) {
                $sth = $pdo->prepare('SELECT id FROM tag WHERE LOWER(title) = LOWER(?)');
                $sth->execute([$tag]);
                $id = $sth->fetchColumn();
            }
            $tags[] = [
                'id' => $id,
                'title' => $tag,
            ];
        }
        $activity['tags'] = $tags;

        $placeholders = substr(str_repeat('(?, ?), ', count($activity['tags'])), 0, -2);
        $sth = $pdo->prepare("INSERT INTO activity_tag (activity_id, tag_id) VALUES $placeholders ON CONFLICT DO NOTHING");
        $params = [];
        foreach ($activity['tags'] as $tag) {
            array_push($params, $activity['id'], $tag['id']);
        }
        $sth->execute($params);
    }

    $pdo->commit();

    header('Location: index.php');
} catch (PDOException $e) {
    $pdo->rollBack();
    echo 'Database error: '.$e->getMessage();
}
