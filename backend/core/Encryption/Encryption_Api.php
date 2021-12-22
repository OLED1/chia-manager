<?php
  namespace ChiaMgmt\Encryption;

  /**
   * The Encryption_Api class is used to encrypt and decrypt string like password before they are saved or loaded from/to the database.
   * @version 0.1.1
   * @author OLED1
   * @see https://www.geeksforgeeks.org/how-to-encrypt-and-decrypt-a-php-string/
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   * @since 0.1.0
   */
  class Encryption_Api{
    /**
     * The cipher algorithmus
     * @var string
     */
    private $ciphering;
    /**
     * The length of the cipher initialization vector (iv) length.
     * @var int
     */
    private $iv_length;
    /**
     * Holds the bitwise disjunction of the flags OPENSSL_RAW_DATA and OPENSSL_ZERO_PADDING.
     * @var string
     */
    private $options;
    /**
     * Holds the initialization vector which is not NULL.
     * @var string
     */
    private $encryption_iv;
    /**
     * The server configuration file.
     * @var array
     */
    private $ini;

    /**
     * The constructur sets the needed above stated private variables.
     */
    public function __construct(){
      $this->ciphering = "AES-128-CTR";
      $this->iv_length = openssl_cipher_iv_length($this->ciphering);
      $this->options = 0;
      $this->encryption_iv = '1234567891011121';
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
    }

    /**
     * Encrypts a cleartext string.
     * @param  string $cleatextstring  A cleartext string which should be decrypted.
     * @return string                  The encrypted string.
     */
    public function encryptString(string $cleatextstring): string
    {
      return openssl_encrypt($cleatextstring, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }

    /**
     * Decrypts an encrypted string.
     * @param  string $encryptedstring   An encrypted string, which is commonly loaded from the db.
     * @return string                    The decrypted string.
     */
    public function decryptString(string $encryptedstring): string
    {
      return openssl_decrypt($encryptedstring, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }
  }
?>
