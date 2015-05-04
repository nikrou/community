DIST=.dist
PLUGIN_NAME=$(shell basename `pwd`)
PLUGIN_VERSION=$(shell grep 'Version:' ./main.inc.php| sed -e 's/.*Version: //')
SOURCE=./*
TARGET=../target

config: clean
	mkdir -p $(DIST)/$(PLUGIN_NAME)
	cp -pr *.php include language sql tpl uploadify \
	CHANGELOG.md COPYING README.md $(DIST)/$(PLUGIN_NAME)/
	find $(DIST) -name '*~' -exec rm \{\} \;

dist: config
	cd $(DIST); \
	mkdir -p $(TARGET); \
	rm $(TARGET)/$(PLUGIN_NAME)-$(PLUGIN_VERSION).zip ; \
	zip -v -r9 $(TARGET)/$(PLUGIN_NAME)-$(PLUGIN_VERSION).zip $(PLUGIN_NAME) ; \
	cd ..

manifest:
	@find ./ -type f|egrep -v '(*~|.git|.gitignore|.dist|target|modele|Makefile|rsync_exclude)'|sed -e 's/\.\///' -e 's/\(.*\)/$(PLUGIN_NAME)\/&/'> ./MANIFEST

clean:
	rm -fr $(DIST)
