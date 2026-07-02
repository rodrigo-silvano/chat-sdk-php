<?php



namespace PHPMailer\PHPMailer;


class Exception extends \Exception
{
    
    public function errorMessage()
    {
        $label = static::class;
        if ($this->getCode() !== 0) {
            $label .= ' #' . (string) $this->getCode();
        }

        return '<strong>' . htmlspecialchars($label, ENT_COMPAT | ENT_HTML401) . "</strong><br />\n";
    }
}
