<?php
// src/FlashMessage.php

class FlashMessage {
    private const SESSION_KEY = 'flash_messages';
    
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }
    
    public static function set(string $message, string $type = 'success') {
        self::init();
        $_SESSION[self::SESSION_KEY][] = [
            'message' => $message,
            'type' => $type
        ];
    }
    
    public static function getAll() {
        self::init();
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        $_SESSION[self::SESSION_KEY] = []; // Leeren nach dem Abrufen
        return $messages;
    }
    
    public static function hasMessages() {
        self::init();
        return !empty($_SESSION[self::SESSION_KEY]);
    }
}