<?php
// Filter.php
class Filter {
    private $words = [];
    private $blocks = ['❗️', '❕', ' 🚫', ' 🔞'];

    public function __construct() {
        $this->load();
    }

    private function preprocess($text) {
        // Harfleri küçült ve ardışık harfleri teke düşür (örn: haaarika -> harika)
        $text = mb_strtolower((string)$text, 'UTF-8');
        return preg_replace('/(.)\1+/u', '$1', $text);
    }

    private function load() {
        $file = file_exists('karaliste.txt') ? 'karaliste.txt' : (file_exists('blacklist.txt') ? 'blacklist.txt' : null);
        
        if ($file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') !== 0) {
                    // Güvenli regex araması için özel karakterleri kaçış karakteriyle işaretle
                    $this->words[] = preg_quote($this->preprocess(trim($line)), '/');
                }
            }
            $this->words = array_unique($this->words);
        }
    }

    public function has_bad_word($text) {
        if (empty($this->words) || empty($text)) {
            return false;
        }
        // Negatif lookahead/lookbehind ile tam kelime eşleşmesi
        $pattern = '/(?<!\p{L})(' . implode('|', $this->words) . ')(?!\p{L})/ui';
        return preg_match($pattern, $this->preprocess($text)) === 1;
    }
}
?>