<?php

final class RegistrationInput
{
    private const STREAMS = ['humanities', 'science', 'business_studies'];
    private const MATH_LEVELS = ['advanced', 'general'];
    private const MAX_RESULTS = 20;

    public static function validate(array $input): array
    {
        $errors = [];
        $fullName = self::text($input['full_name'] ?? '');
        $email = strtolower(self::text($input['email'] ?? ''));
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        $schoolStream = self::text($input['school_stream'] ?? '');
        $mathLevel = self::text($input['math_level'] ?? '');

        if ($fullName === '' || strlen($fullName) > 150) {
            $errors[] = 'Enter your full name (up to 150 characters).';
        }

        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid email address.';
        }

        if (strlen($password) < 8 || strlen($password) > 72) {
            $errors[] = 'Your password must be between 8 and 72 bytes.';
        }

        if ($schoolStream !== '' && !in_array($schoolStream, self::STREAMS, true)) {
            $errors[] = 'Choose a valid school stream.';
        }

        if ($mathLevel !== '' && !in_array($mathLevel, self::MATH_LEVELS, true)) {
            $errors[] = 'Choose a valid mathematics level.';
        }

        $results = [];
        $inputResults = $input['results'] ?? [];
        if (!is_array($inputResults)) {
            $errors[] = 'Enter school subjects and results.';
            $inputResults = [];
        }

        if (count($inputResults) > self::MAX_RESULTS) {
            $errors[] = 'Enter no more than 20 subject results.';
        }

        foreach (array_slice($inputResults, 0, self::MAX_RESULTS) as $index => $row) {
            if (!is_array($row)) {
                $errors[] = 'Each result must include a subject and a grade or result.';
                continue;
            }

            $subject = self::text($row['subject'] ?? '');
            $result = self::text($row['result'] ?? '');

            if ($subject === '' && $result === '') {
                continue;
            }

            if ($subject === '' || $result === '') {
                $errors[] = 'Complete both the subject and result for entry ' . ((int) $index + 1) . '.';
                continue;
            }

            if (strlen($subject) > 100 || strlen($result) > 30) {
                $errors[] = 'Subject names must be at most 100 characters and results at most 30 characters.';
                continue;
            }

            $results[] = [
                'subject' => $subject,
                'result' => $result,
            ];
        }

        if (count($results) === 0) {
            $errors[] = 'Enter at least one school subject and its result.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'full_name' => $fullName,
                'email' => $email,
                'password' => $password,
                'school_stream' => $schoolStream !== '' ? $schoolStream : null,
                'math_level' => $mathLevel !== '' ? $mathLevel : null,
                'results' => $results,
            ],
        ];
    }

    private static function text($value): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
