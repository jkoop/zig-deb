# zig-deb

Here is the script I use to create `.deb` files from precompiled binaries of [Zig], which I host on [my apt packages server][packagesJK].

I am not endorsed by [the Zig Software Foundation][zsf]. I just like their project, and see that they aren't publishing `.deb` files of their own.

## Usage

1. Go to https://packages.joekoop.com and run the command at the top of the page.
1. Update your local indecies: `sudo apt update`
1. Install Zig: `sudo apt install zig`

[Zig]: https://ziglang.org/
[packagesJK]: http://packages.joekoop.com/
[zsf]: https://ziglang.org/zsf/
