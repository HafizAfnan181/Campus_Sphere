
<?php
class JWT {
    // Generated fresh for this project. Before you deploy this for real
    // public use, generate your OWN secret instead of reusing this one:
    //   php -r "echo bin2hex(random_bytes(32));"
    private static $secret_key = "2a56892c64d8ad249fe1ac6c8a70853347c8cad6ac11595926d8cace1c5e774";
    private static $algorithm = "HS256";

    public static function generateToken($user_id, $username) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user_id,
            'username' => $username,
            'exp' => time() + (86400 * 30) // 30 days
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret_key, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function validateToken($token) {
        $parts = explode('.', $token);
        if (count($parts) != 3) return false;

        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
        $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[2]));

        $valid_signature = hash_hmac('sha256', $parts[0] . "." . $parts[1], self::$secret_key, true);

        // hash_equals() = constant-time comparison, so an attacker can't
        // guess the signature byte-by-byte via response-timing.
        if (!hash_equals($valid_signature, $signature)) return false;

        $data = json_decode($payload, true);
        if (!isset($data['exp']) || $data['exp'] < time()) return false;

        return $data;
    }
}
?>