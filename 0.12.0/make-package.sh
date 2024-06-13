#/bin/bash
set -e

ARCH='amd64'
ARCH_DL='x86_64'
DEB_FILE_NAME='zig_0.12.0_'$ARCH

echo 'Downloading zig and its signature...'
wget --continue --quiet --show-progress \
    'https://ziglang.org/download/0.12.0/zig-linux-'$ARCH_DL'-0.12.0.tar.xz' \
    'https://ziglang.org/download/0.12.0/zig-linux-'$ARCH_DL'-0.12.0.tar.xz.minisig'

echo 'Verifying signature...'
minisign -Vm 'zig-linux-'$ARCH_DL'-0.12.0.tar.xz'

echo 'Extracting tar...'
tar xf 'zig-linux-'$ARCH_DL'-0.12.0.tar.xz'

echo 'Staging package...'
rm -rf $DEB_FILE_NAME
mkdir --parents \
    $DEB_FILE_NAME'/DEBIAN' \
    $DEB_FILE_NAME'/usr/local/bin' \
    $DEB_FILE_NAME'/usr/local/lib';
cp -l 'zig-linux-'$ARCH_DL'-0.12.0/zig' $DEB_FILE_NAME'/usr/local/bin'
cp -lr 'zig-linux-'$ARCH_DL'-0.12.0/lib' $DEB_FILE_NAME'/usr/local/lib/zig'
SIZE=$(du --block-size=1024 --summarize $DEB_FILE_NAME'/usr' | grep -Eo '^[0-9]*')

sed 's/ARCH/'$ARCH'/' 'DEBIAN-control' |
    sed 's/SIZE/'$SIZE'/' > $DEB_FILE_NAME'/DEBIAN/control'

echo 'Building package...'
dpkg-deb --build $DEB_FILE_NAME
echo
echo 'DONE! -- '$DEB_FILE_NAME'.deb'
echo
