#!/usr/bin/php8.1
<?php

/**
 * Key: arch as called by Zig
 * Value: arch as called by Debian packaging system
 */
const ARCH_MAP = [
    // "aarch64" => "arm64",
    // "i386" => "i386",
    "x86_64" => "amd64",
    // "x86" => "i386",
];

const WGET = "wget --quiet --show-progress -O 'tarball' "; // this must end with a space
const COMPLETE_LIST_PATH = "complete.list";

chdir(__DIR__); // so we can be called from anywhere

if (!file_exists(COMPLETE_LIST_PATH)) file_put_contents(COMPLETE_LIST_PATH, "");
$complete = explode("\n", file_get_contents(COMPLETE_LIST_PATH));
$complete = array_filter($complete, "strlen");

$releases = json_decode(file_get_contents("https://ziglang.org/download/index.json"));

foreach ($releases as $version => $files) {
    if ($version == "master") continue;
    foreach ($files as $arch => $props) {
        $package_date = date("Y-m-d");

        if (!str_ends_with($arch, "-linux")) {
            // debug("Arch not linux: %s", $arch);
            continue;
        }

        // remove "-linux" from the end
        $arch = substr($arch, 0, strpos($arch, "-linux"));

        if (!isset(ARCH_MAP[$arch])) {
            warning("Arch not supported: %s", $arch);
            continue;
        }

        $arch = ARCH_MAP[$arch]; // convert to Debiany name
        $minor_version = substr($version, 0, strrpos($version, "."));
        $package_name = "zig" . $minor_version;
        $deb_file_name = $package_name . '_' . $version . '_' . $arch;

        if (in_array($deb_file_name, $complete)) {
            // info("Already packaged: %s", $deb_file_name);
            continue;
        }

        passthru("rm -rf tarball tarball.d $deb_file_name $deb_file_name.deb");
        printf("\n\tBuilding \"%s.deb\"\n\n", $deb_file_name);

        $exit_code = -1;
        info("Downloading tarball: %s", $props->tarball);
        passthru(WGET . escapeshellarg($props->tarball), $exit_code);
        if ($exit_code != 0) {
            error("wget exit code non-zero: %d", $exit_code);
            continue;
        }

        $exit_code = -1;
        info("Extracting tarball", $props->tarball);
        mkdir("tarball.d");
        chdir("tarball.d");
        passthru('tar xf ../tarball', $exit_code);
        if ($exit_code != 0) {
            error("tar exit code non-zero: %d", $exit_code);
            continue;
        }
        chdir(__DIR__);

        info("Staging package: %s", $deb_file_name);
        mkdir($deb_file_name . '/opt/zig/' . $package_name, recursive: true);
        mkdir($deb_file_name . '/usr/sbin', recursive: true);
        chdir($deb_file_name);
        passthru('cp -l ../tarball.d/*/zig opt/zig/' . $package_name);
        passthru('cp -lr ../tarball.d/*/lib opt/zig/' . $package_name);
        passthru('ln -s /opt/zig/' . $package_name . '/zig usr/sbin/' . $package_name);

        $installed_size = trim(`du --block-size=1024 --summarize . | grep -Eo '^[0-9]*'`);
        mkdir("DEBIAN");
        file_put_contents("DEBIAN/control", <<<TXT
        Package: $package_name
        Version: $version
        Architecture: $arch
        Maintainer: Zig Software Foundation <https://ziglang.org/zsf>
        Vendor: Joe Koop <https://github.com/jkoop>
        Installed-Size: $installed_size
        Section: devel
        Priority: optional
        Homepage: https://ziglang.org/
        Description: A programming language for robust and efficient software
         Zig[1] is a general-purpose programming language and toolchain for
         maintaining robust, optimal and reusable software.
         .
         See also: the language docs[2], std docs[3], and release notes[4]
         .
         Built by ZSF on $files->date; packaged for Debian by Joe Koop[5] on $package_date
         .
         1: https://ziglang.org
         2: $files->docs
         3: $files->stdDocs
         4: $files->notes
         5: https://github.com/jkoop
        TXT . "\n");
        chdir(__DIR__);

        $exit_code = -1;
        info("Building package: %s", $deb_file_name);
        passthru("dpkg-deb --build $deb_file_name", $exit_code);
        if ($exit_code != 0) {
            error("dpkg-deb exit code non-zero: %d", $exit_code);
            continue;
        }

        info("Cleaning up");
        passthru('rm -rf tarball tarball.d ' . $deb_file_name);

        $complete[] = $deb_file_name;
        file_put_contents(COMPLETE_LIST_PATH, implode("\n", $complete));
    }
}
passthru('rm -rf tarball tarball.d');

// meta packages
foreach ($releases as $version => $files) {
    if ($version == "master") continue;
    foreach ($files as $arch => $props) {
        $package_date = date("Y-m-d");

        if (!str_ends_with($arch, "-linux")) {
            // debug("Arch not linux: %s", $arch);
            continue;
        }

        // remove "-linux" from the end
        $arch = substr($arch, 0, strpos($arch, "-linux"));

        if (!isset(ARCH_MAP[$arch])) {
            warning("Arch not supported: %s", $arch);
            continue;
        }

        $arch = ARCH_MAP[$arch]; // convert to Debiany name
        $minor_version = substr($version, 0, strrpos($version, "."));
        $package_name = "zig";
        $deb_file_name = $package_name . '_' . $version . '_' . $arch;

        if (in_array($deb_file_name, $complete)) {
            // info("Already packaged: %s", $deb_file_name);
            continue;
        }

        passthru("rm -rf $deb_file_name $deb_file_name.deb");
        printf("\n\tBuilding meta-package \"%s.deb\"\n\n", $deb_file_name);

        info("Staging package: %s", $deb_file_name);
        mkdir($deb_file_name . '/usr/sbin', recursive: true);
        chdir($deb_file_name);
        passthru('ln -s /usr/sbin/zig' . $minor_version . ' usr/sbin/' . $package_name);

        $installed_size = trim(`du --block-size=1024 --summarize . | grep -Eo '^[0-9]*'`);
        mkdir("DEBIAN");
        file_put_contents("DEBIAN/control", <<<TXT
        Package: $package_name
        Version: $version
        Architecture: $arch
        Maintainer: Zig Software Foundation <https://ziglang.org/zsf>
        Vendor: Joe Koop <https://github.com/jkoop>
        Installed-Size: $installed_size
        Depends: zig$minor_version (=$version)
        Section: devel
        Priority: optional
        Homepage: https://ziglang.org/
        Description: A programming language for robust and efficient software
         Zig[1] is a general-purpose programming language and toolchain for
         maintaining robust, optimal and reusable software.
         .
         (This is a meta-package that depends on zig$minor_version for version $version)
         .
         Built by ZSF on $files->date; packaged for Debian by Joe Koop[2] on $package_date
         .
         1: https://ziglang.org
         2: https://github.com/jkoop
        TXT . "\n");
        chdir(__DIR__);

        $exit_code = -1;
        info("Building package: %s", $deb_file_name);
        passthru("dpkg-deb --build $deb_file_name", $exit_code);
        if ($exit_code != 0) {
            error("dpkg-deb exit code non-zero: %d", $exit_code);
            continue;
        }

        info("Cleaning up");
        passthru('rm -rf ' . $deb_file_name);

        $complete[] = $deb_file_name;
        file_put_contents(COMPLETE_LIST_PATH, implode("\n", $complete));
    }
}

info("DONE!");

exit;

function info(string $fmt, ...$values): void {
    printf("INFO\t" . $fmt . "\n", ...$values);
}

function warning(string $fmt, ...$values): void {
    printf("WARN\t" . $fmt . "\n", ...$values);
}

function error(string $fmt, ...$values): void {
    printf("ERROR\t" . $fmt . "\n", ...$values);
}
