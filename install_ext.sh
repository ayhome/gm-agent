wget https://pecl.php.net/get/hprose-1.6.6.tgz
tar zxvfp hprose-1.6.6.tgz
cd hprose-1.6.6
phpize
./configure
make
make install
echo "extension = hprose.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
cd ..
