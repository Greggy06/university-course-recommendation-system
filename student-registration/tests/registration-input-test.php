<?php

require_once dirname(__DIR__) . '/src/RegistrationInput.php';

$baseInput = [
    'full_name' => 'A Student',
    'email' => 'student@example.edu',
    'password' => 'student-password',
    'results' => [
        ['subject' => 'Biology', 'result' => 'A'],
    ],
];

$valid = RegistrationInput::validate($baseInput);
if ($valid['errors'] !== [] || $valid['data']['school_stream'] !== null) {
    throw new RuntimeException('A valid registration with an optional stream should pass.');
}

$withStream = RegistrationInput::validate($baseInput + [
    'school_stream' => 'science',
    'math_level' => 'advanced',
]);
if ($withStream['errors'] !== [] || $withStream['data']['math_level'] !== 'advanced') {
    throw new RuntimeException('A valid registration with stream details should pass.');
}

$incomplete = RegistrationInput::validate(array_replace($baseInput, [
    'results' => [
        ['subject' => 'Biology', 'result' => ''],
    ],
]));
if ($incomplete['errors'] === []) {
    throw new RuntimeException('An incomplete subject/result pair must be rejected.');
}

$invalidStream = RegistrationInput::validate($baseInput + [
    'school_stream' => 'not-a-stream',
]);
if ($invalidStream['errors'] === []) {
    throw new RuntimeException('An unsupported school stream must be rejected.');
}

$longPassword = $baseInput;
$longPassword['password'] = str_repeat('p', 73);
if (RegistrationInput::validate($longPassword)['errors'] === []) {
    throw new RuntimeException('Passwords longer than the supported hashing limit must be rejected.');
}

$tooManyResults = $baseInput;
$tooManyResults['results'] = array_fill(0, 21, ['subject' => 'Physics', 'result' => 'B']);
if (RegistrationInput::validate($tooManyResults)['errors'] === []) {
    throw new RuntimeException('More than 20 results must be rejected.');
}

echo "Registration input tests passed.\n";
