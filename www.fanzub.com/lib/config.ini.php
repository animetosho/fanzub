; <?php exit(); // coding: latin1
; /*

[db]
server = "localhost"
database = "fanzub"
username = "fanzub"
password = "DATABASE_PASSWORD"

[sphinx]
; to use Unix socket, enter a pathname for the host, eg "/tmp/sphinx.sock"
host = "localhost"
port = 9312

[url]
; please don't use special HTML/JS characters in the URL, as I'm too lazy to escape them everywhere...
;base = "http://fanzub.com/"
base = "/"
basefull = "http://fanzub.com/"
;assets = "http://fanzub.com/"
assets = "/"
nzb = "http://fanzub.com/nzb"
rss = "http://fanzub.com/rss"
mail = "info@fanzub.com"

[path]
journal = "/home/fanzub/www.fanzub.com/data/journal.db"
template = "/home/fanzub/www.fanzub.com/template"
nzb = "/home/fanzub/nzb"

[cache]
; type can be apc, memcache (hard-coded connect to localhost:11211) or null (no cache)
type = "memcache"
name = "Fanzub"

; */
; ?>
