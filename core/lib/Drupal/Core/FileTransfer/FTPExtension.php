<?php

/**
 * @file
 * Contains \Drupal\Core\FileTransfer\FTPExtension.
 */

namespace Drupal\Core\FileTransfer;

/**
 * Defines a file transfer class using the PHP FTP extension.
 */
class FTPExtension extends FTP implements ChmodInterface {

  /**
   * Implements Drupal\Core\FileTransfer\FileTransfer::connect().
   */
  public function connect() {
    $this->connection = ftp_connect($this->hostname, $this->port);

    if (!$this->connection) {
      throw new FileTransferException("Cannot connect to FTP Server, check settings");
    }
    if (!ftp_login($this->connection, $this->username, $this->password)) {
      throw new FileTransferException("Cannot log in to FTP server. Check username and password");
    }
  }

  /**
   * Implements Drupal\Core\FileTransfer\FileTransfer::copyFileJailed().
   */
  protected function copyFileJailed($source, $destination) {
    if (!@ftp_put($this->connection,  $destination, $source, FTP_BINARY)) {
      throw new FileTransferException("Cannot move @source to @destination", NULL, array("@source" => $source, "@destination" => $destination));
    }
  }

  /**
   * Implements Drupal\Core\FileTransfer\FileTransfer::createDirectoryJailed().
   */
  protected function createDirectoryJailed($directory) {
    if (!ftp_mkdir($this->connection, $directory)) {
      throw new FileTransferException("Cannot create directory @directory", NULL, array("@directory" => $directory));
    }
  }

  /**
   * Implements Drupal\Core\FileTransfer\FileTransfer::removeDirectoryJailed().
   */
  protected function removeDirectoryJailed($directory) {
    $pwd = ftp_pwd($this->connection);
    if (!ftp_chdir($this->connection, $directory)) {
      throw new FileTransferException("Unable to change to directory @directory", NULL, array('@directory' => $directory));
    }
    $list = @ftp_nlist($this->connection, '.');
    if (!$list) {
      $list = array();
    }
    foreach ($list as $item) {
      if ($item == '.' || $item == '..') {
        continue;
      }
      if (@ftp_chdir($this->connection, $item)) {
        ftp_cdup($this->connection);
        $this->removeDirectory(ftp_pwd($this->connection) . '/' . $item);
      }
      else {
        $this->removeFile(ftp_pwd($this->connection) . '/' . $item);
      }
    }
    ftp_chdir($this->connection, $pwd);
    if (!ftp_rmdir($this->connection, $directory)) {
      throw new FileTransferException("Unable to remove to directory @directory", NULL, array('@directory' => $directory));
    }
  }

  /**
   * Implements Drupal\Core\FileTransfer\FileTransfer::removeFileJailed().
   */
  protected function removeFileJailed($destination) {
    if (!ftp_delete($this->connection, $destination)) {
      throw new FileTransferException("Unable to remove to file @file", NULL, array('@file' => $destination));
    }
  }

  /**
   * Implements Drupal\Core\FileTransfer\FileTransfer::isDirectory().
   */
  public function isDirectory($path) {
    $result = FALSE;
    $curr = ftp_pwd($this->connection);
    if (@ftp_chdir($this->connection, $path)) {
      $result = TRUE;
    }
    ftp_chdir($this->connection, $curr);
    return $result;
  }

  /**
   * Implements Drupal\Core\FileTransfer\FileTransfer::isFile().
   */
  public function isFile($path) {
    return ftp_size($this->connection, $path) != -1;
  }

  /**
   * Implements Drupal\Core\FileTransfer\ChmodInterface::chmodJailed().
   */
  function chmodJailed($path, $mode, $recursive) {
    if (!ftp_chmod($this->connection, $mode, $path)) {
      throw new FileTransferException("Unable to set permissions on %file", NULL, array('%file' => $path));
    }
    if ($this->isDirectory($path) && $recursive) {
      $filelist = @ftp_nlist($this->connection, $path);
      if (!$filelist) {
        //empty directory - returns false
        return;
      }
      foreach ($filelist as $file) {
        $this->chmodJailed($file, $mode, $recursive);
      }
    }
  }
}
