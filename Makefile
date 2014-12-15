DESTDIR ?=
PREFIX ?= /usr/local

all:
	@:

install: all
	mkdir -p $(DESTDIR)$(PREFIX)/bin
	cp spritifier-cli.php $(DESTDIR)$(PREFIX)/bin/spritifier
	chmod 0755 $(DESTDIR)$(PREFIX)/bin/spritifier
	mkdir -p $(DESTDIR)$(PREFIX)/share/spritifier
	cp -r classes $(DESTDIR)$(PREFIX)/share/spritifier
	mkdir -p $(DESTDIR)$(PREFIX)/share/man/man1
	cp spritifier.1 $(DESTDIR)$(PREFIX)/share/man/man1

clean:
	@:

.PHONY: all install clean
