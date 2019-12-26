Pico Editor Plugin
==================

Provides an online Markdown editor and file manager for Pico.

Install
-------

1. Extract a copy of the "PicoEditor" folder to your Pico install "plugins" folder
   - or `git clone https://github.com/astappiev/pico-editor.git PicoEditor`
2. Place the following in your `config/config.yml` file
```yml
# Pico Editor Configuration
PicoEditor:
    enabled: true
    password: SHA512-HASHED-PASSWORD
    url: editor-url
```
3. Create your `SHA-512` hashed password (https://sha512.online/) and replace in config
4. Open `http://<your domain>/?editor-url` and login
