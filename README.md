# PHP Youtube Downloader

php-youtube-downloader download video from Youtube website

This script is inspired from [youtube-dl](https://github.com/rg3/youtube-dl) (specially the encrypted part)

# Installation

### Requirements

  - PHP >= 5.4
  - [pecl_http extension](http://www.php.net/manual/en/http.install.php) >= 0.21.0
  - [Composer](https://getcomposer.org/)

### Installation steps

     # clone this repository
     $ git clone https://github.com/Gin-san/php-youtube-downloader.git
     $ cd php-youtube-downloader
     # install packages using composer
     $ composer.phar install

# Usage

     $ ./php-yt-dl -v [CODE_VIDEO]

# TODO

  - execute download
  - add an option to ask which quality to download
  - manage multiple video
