<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$errors = [];
$old = [];
$success = isset($_SESSION['registration_success']) && $_SESSION['registration_success'] === true;
unset($_SESSION['registration_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    if (!has_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'This form expired or could not be verified. Please try again.';
    } else {
        $validation = RegistrationInput::validate($_POST);
        $errors = $validation['errors'];

        if ($errors === []) {
            $connection = null;
            try {
                $connection = database_connection();
                $connection->beginTransaction();

                $studentInsert = $connection->prepare(
                    'INSERT INTO students (full_name, email, password_hash, school_stream, math_level)
                     VALUES (:full_name, :email, :password_hash, :school_stream, :math_level)'
                );
                $studentInsert->execute([
                    'full_name' => $validation['data']['full_name'],
                    'email' => $validation['data']['email'],
                    'password_hash' => password_hash($validation['data']['password'], PASSWORD_DEFAULT),
                    'school_stream' => $validation['data']['school_stream'],
                    'math_level' => $validation['data']['math_level'],
                ]);

                $studentId = (int) $connection->lastInsertId();
                $resultInsert = $connection->prepare(
                    'INSERT INTO student_results (student_id, subject, result)
                     VALUES (:student_id, :subject, :result)'
                );

                foreach ($validation['data']['results'] as $result) {
                    $resultInsert->execute([
                        'student_id' => $studentId,
                        'subject' => $result['subject'],
                        'result' => $result['result'],
                    ]);
                }

                $connection->commit();
                $_SESSION['registration_success'] = true;
                header('Location: register.php', true, 303);
                exit;
            } catch (PDOException $exception) {
                if ($connection instanceof PDO && $connection->inTransaction()) {
                    $connection->rollBack();
                }

                error_log('Student registration database error: ' . $exception->getMessage());
                if ($exception->getCode() === '23000') {
                    $errors[] = 'An account already exists for that email address.';
                } else {
                    $errors[] = 'Registration could not be saved. Check the database setup and try again.';
                }
            } catch (RuntimeException $exception) {
                error_log('Student registration configuration error: ' . $exception->getMessage());
                $errors[] = $exception->getMessage();
            }
        }
    }
}

$resultRows = $old['results'] ?? [];
if (!is_array($resultRows)) {
    $resultRows = [];
}
$resultRows = array_slice(array_values($resultRows), 0, 20);
if ($resultRows === []) {
    $resultRows = [['subject' => '', 'result' => '']];
}

$schoolStream = is_string($old['school_stream'] ?? null) ? $old['school_stream'] : '';
$mathLevel = is_string($old['math_level'] ?? null) ? $old['math_level'] : '';
$fullName = is_string($old['full_name'] ?? null) ? $old['full_name'] : '';
$email = is_string($old['email'] ?? null) ? $old['email'] : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student registration</title>
    <link rel="stylesheet" href="assets/registration.css">
    <script src="assets/registration.js" defer></script>
</head>
<body>
<main>
    <h1>Student registration</h1>
    <p>Register your account and add your school results. Your school stream is
        optional now, but is needed before course recommendations can be made.</p>

    <?php if ($success): ?>
        <p class="notice success" role="status">Registration completed successfully.</p>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="notice error" role="alert">
            <p>Please review the following:</p>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="register.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

        <div class="form-grid">
            <label>
                Full name
                <input name="full_name" maxlength="150" autocomplete="name" required value="<?= h($fullName) ?>">
            </label>
            <label>
                Email address
                <input type="email" name="email" maxlength="254" autocomplete="email" required value="<?= h($email) ?>">
            </label>
        </div>

        <label>
            Password
            <input type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
        </label>

        <h2>High-school or secondary-school details</h2>
        <p class="hint">You can add your stream now or leave it blank and provide it before recommendations.</p>

        <div class="form-grid">
            <label>
                Subject stream
                <select name="school_stream">
                    <option value="">Not provided</option>
                    <option value="humanities" <?= $schoolStream === 'humanities' ? 'selected' : '' ?>>Humanities</option>
                    <option value="science" <?= $schoolStream === 'science' ? 'selected' : '' ?>>Science</option>
                    <option value="business_studies" <?= $schoolStream === 'business_studies' ? 'selected' : '' ?>>Business Studies</option>
                </select>
            </label>
            <label>
                Mathematics level
                <select name="math_level">
                    <option value="">Not provided</option>
                    <option value="advanced" <?= $mathLevel === 'advanced' ? 'selected' : '' ?>>Advanced Maths</option>
                    <option value="general" <?= $mathLevel === 'general' ? 'selected' : '' ?>>General Maths</option>
                </select>
            </label>
        </div>

        <h2>School subjects and results</h2>
        <p class="hint">Add at least one subject and its grade or result. Examples include Biology, Physics, Chemistry, Engineering, STEM, or Humanities subjects.</p>
        <div id="results-list">
            <?php foreach ($resultRows as $index => $row): ?>
                <?php
                $subject = is_array($row) && is_string($row['subject'] ?? null) ? $row['subject'] : '';
                $result = is_array($row) && is_string($row['result'] ?? null) ? $row['result'] : '';
                ?>
                <div class="result-row">
                    <label>
                        Subject
                        <input name="results[<?= (int) $index ?>][subject]" maxlength="100" autocomplete="off" value="<?= h($subject) ?>">
                    </label>
                    <label>
                        Grade or result
                        <input name="results[<?= (int) $index ?>][result]" maxlength="30" autocomplete="off" value="<?= h($result) ?>">
                    </label>
                    <?php if (count($resultRows) > 1): ?>
                        <button type="button" class="remove-result" aria-label="Remove this result">Remove</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-result">Add another result</button>
        <button type="submit">Register</button>
    </form>
</main>
</body>
</html>
