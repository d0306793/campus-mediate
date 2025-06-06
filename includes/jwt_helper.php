<?php
/**
 * Simple JWT helper functions for encoding and decoding tokens
 */

class JWT {
    /**
     * Encode a payload into a JWT string
     * 
     * @param array $payload The payload to encode
     * @param string $key The secret key
     * @param string $alg The algorithm to use (default: HS256)
     * @return string The JWT
     */
    public static function encode($payload, $key, $alg = 'HS256') {
        // Define the header
        $header = [
            'typ' => 'JWT',
            'alg' => $alg
        ];
        
        // Encode header and payload
        $header_encoded = self::base64UrlEncode(json_encode($header));
        $payload_encoded = self::base64UrlEncode(json_encode($payload));
        
        // Create signature
        $signature = self::sign("$header_encoded.$payload_encoded", $key, $alg);
        $signature_encoded = self::base64UrlEncode($signature);
        
        // Create JWT
        return "$header_encoded.$payload_encoded.$signature_encoded";
    }
    
    /**
     * Decode a JWT string
     * 
     * @param string $jwt The JWT to decode
     * @param string $key The secret key
     * @param string $alg The algorithm to use (default: HS256)
     * @return array The decoded payload
     * @throws Exception If token is invalid or expired
     */
    public static function decode($jwt, $key, $alg = 'HS256') {
        // Split the JWT
        $parts = explode('.', $jwt);
        
        if (count($parts) != 3) {
            throw new Exception('Invalid token format');
        }
        
        list($header_encoded, $payload_encoded, $signature_encoded) = $parts;
        
        // Verify signature
        $signature = self::base64UrlDecode($signature_encoded);
        $verified = self::verify("$header_encoded.$payload_encoded", $signature, $key, $alg);
        
        if (!$verified) {
            throw new Exception('Signature verification failed');
        }
        
        // Decode payload
        $payload = json_decode(self::base64UrlDecode($payload_encoded), true);
        
        // Check if token has expired
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('Token has expired');
        }
        
        return $payload;
    }
    
    /**
     * Create a signature
     */
    private static function sign($msg, $key, $alg) {
        if ($alg === 'HS256') {
            return hash_hmac('sha256', $msg, $key, true);
        }
        throw new Exception('Algorithm not supported');
    }
    
    /**
     * Verify a signature
     */
    private static function verify($msg, $signature, $key, $alg) {
        if ($alg === 'HS256') {
            $expected = self::sign($msg, $key, $alg);
            return hash_equals($expected, $signature);
        }
        throw new Exception('Algorithm not supported');
    }
    
    /**
     * Base64Url encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64Url decode
     */
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}