#!/usr/bin/env bash
# Created from the .travis.yml file
# Last update 2018-08-20
# Update this file when/if updating the .travis.yml file removing the lines that would run the tests: the script part.
# Used in Travis CI debug simply by calling it like this from the $TRAVIS_BUILD_DIR folder
# cd $TRAVIS_BUILD_DIR && sh ./tests/travis/build.sh

cd $TRAVIS_BUILD_DIR

export wpDbName="wordpress" wpLoaderDbName="wordpress" wpDbPrefix="wp_" wpAdminUsername="admin" wpAdminPassword="admin" wpRootFolder="/tmp/wordpress"

# set up DB
mysql -e "CREATE DATABASE IF NOT EXISTS $wpDbName;" -uroot
mysql -e "CREATE DATABASE IF NOT EXISTS $wpLoaderDbName;" -uroot

if [ ! -d "$wpRootFolder" ]; then
	# set up folders
	mkdir -p $HOME/tools $wpRootFolder

	# install wp-cli
	wget https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -P /tmp/tools/
	chmod +x /tmp/tools/wp-cli.phar && mv /tmp/tools/wp-cli.phar /tmp/tools/wp
	export PATH=$PATH:/tmp/tools:vendor/bin

	# install Apache and WordPress setup scripts
	git clone https://github.com/lucatume/travis-apache-setup.git /tmp/tools/travis-apache-setup
	chmod +x /tmp/tools/travis-apache-setup/apache-setup.sh
	chmod +x /tmp/tools/travis-apache-setup/wp-install.sh
	ln -s /tmp/tools/travis-apache-setup/apache-setup.sh /tmp/tools/apache-setup
	ln -s /tmp/tools/travis-apache-setup/wp-install.sh /tmp/tools/wp-install

	# download and install WordPress
	if [ $wpMultisite = "1" ]; then
		wp-install --dir="$wpRootFolder" --dbname="$wpDbName" --dbuser="root" --dbpass="" --dbprefix="$wpDbPrefix" --multisite --subdomains --domain="$wpUrl" --title="Test" --admin_user="$wpAdminUsername" --admin_password="$wpAdminPassword" --admin_email="admin@$wpUrl" --theme="twentysixteen" --empty
	fi

	if [ $wpMultisite = "0" ]; then
		wp-install --dir="$wpRootFolder" --dbname="$wpDbName" --dbuser="root" --dbpass="" --dbprefix="$wpDbPrefix" --domain="$wpUrl" --title="Test" --admin_user="$wpAdminUsername" --admin_password="$wpAdminPassword" --admin_email="admin@$wpUrl" --theme="twentysixteen" --empty
	fi
fi

if [ ! -d "$wpRootFolder/wp-content/$pluginsFolder" ]; then
	# make the folder if it needs to be made
	mkdir $wpRootFolder/wp-content/$pluginsFolder
fi

# move the plugin into WordPress folder
mv $TRAVIS_BUILD_DIR/wp-content/$pluginsFolder/$pluginSlug $wpRootFolder/wp-content/$pluginsFolder/$pluginSlug

# set up Apache virtual host
sudo env "PATH=$PATH" apache-setup --host="127.0.0.1" --url="$wpUrl" --dir="$wpRootFolder"

# get back to the plugin dir
cd $wpRootFolder/wp-content/$pluginsFolder/$pluginSlug

# update composer
composer update --prefer-dist

if [ $pluginsFolder = "plugins" ]; then
	# activate the plugin in WordPress now that we have all dependencies
	wp plugin activate $pluginSlug --path=$wpRootFolder
fi

# flush rewrite rules
printf  "apache_modules:\n\t- mod_rewrite" > $wpRootFolder/wp-cli.yml
wp rewrite structure '/%postname%/' --hard --path=$wpRootFolder
