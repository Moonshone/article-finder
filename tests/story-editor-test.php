<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/stories.php';

function assert_story(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$unsafe = '<p class="story-align-center arbitrary" style="color:red" onclick="alert(1)">'
    . '<span class="story-font-24 evil" style="font-size:1000px"><strong>Fett</strong></span>'
    . '<em>Kursiv</em><a href="javascript:alert(1)" onerror="alert(1)">Link</a>'
    . '<script>alert(1)</script><iframe src="https://evil.test"></iframe></p>';
$safe = sanitize_story_html($unsafe);

assert_story(str_contains($safe, 'class="story-align-center"'), 'whitelisted alignment is retained');
assert_story(str_contains($safe, 'class="story-font-24"'), 'whitelisted pixel size is retained');
assert_story(str_contains($safe, '<strong>Fett</strong>') && str_contains($safe, '<em>Kursiv</em>'), 'inline formatting is retained');
foreach (['arbitrary', 'evil', 'style=', 'onclick=', 'onerror=', 'javascript:', '<script', '<iframe'] as $blocked) {
    assert_story(!str_contains($safe, $blocked), "unsafe value was retained: {$blocked}");
}

[$errors, $values] = validate_story_input([
    'title' => '<p class="story-align-right"><strong>Meine Story</strong></p>',
    'excerpt' => '<p><span class="story-font-18">Kurz</span></p>',
    'content' => '<p class="story-align-center"><span class="story-font-24"><strong>Inhalt</strong></span></p>',
    'status' => 'draft',
]);
assert_story($errors === [], 'valid rich story input is accepted');
assert_story(story_plain_text($values['title']) === 'Meine Story', 'technical title is plain text');
assert_story(str_contains($values['content'], 'story-font-24'), 'formatting survives validation');

echo "All story editor tests passed.\n";
