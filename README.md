# supreme-rotary-phone
CLI script to find files and POST them based on rules set up in JSON

---

How it works:

There is a .directories file which is a JSON options file. This lists the directories to be checked.
  Directory paths should be full, absolute paths, or paths relative to this file.
  Paths starting with a / will be assumed to be absolute. paths starting with anything
  else will be assumed to be relative.

Inside each directory listed you will need a .po2go file, which is also a JSON options file. This will list patterns usable to the PHP `glob()` function. and a URL. Any files matching the pattern with be POSTed to the URL.
  
  - Pattern uses PHP's glob regex. This is different from PCRE.
    - * matches 0 or more of any character except /
    - ? matches exactly 1 of any character except /
  - url should be capable of receiving a POST with the contents of the file as a raw data string.

---

Single run usage: `php fileMover.php`

CRON usage: `*/5  *  *  *  * php fileMover.php`

---
