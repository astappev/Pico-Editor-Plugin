# Pico Editor Plugin

Provides an online Markdown editor and file manager for Pico CMS.

This is a fork of [theshka/Pico-Editor-Plugin](https://github.com/theshka/Pico-Editor-Plugin) which is also fork of [gilbitron/Pico-Editor-Plugin](https://github.com/gilbitron/Pico-Editor-Plugin).

## Features
- Works with the latest version of Pico CMS (as of now)
- Tree-like website structure
- Edit pages
- Manage pages
    * create a file (page)
    * create a sub-folder
    * create a file in a sub-folder
    * edit title of the file (rename)
    * delete a file
- Password protected access with customizable URL

## Install
1. Extract a copy of the plugin into your Pico "plugins" folder (should be plugins/PicoEditor/PicoEditor.php)
   - via Composer `composer require astappiev/pico-editor dev-master`
   - or `cd plugins && git clone https://github.com/astappiev/pico-editor.git PicoEditor`
2. Open `http://<your domain>/?editor`, update password (check [Configuration](#configuration) or follow instruction on the page) and login

## Configuration
The configuration can be specified in `config/config.yml`
```yml
# Pico Editor Configuration
PicoEditor:
    enabled: true                           # Force the plugin to be enabled or disabled
    password: SHA512-HASHED-PASSWORD        # You have to use your own password (you should use hash, not raw password! https://sha512.online/)
    url: editor                             # You can change editor url
```
