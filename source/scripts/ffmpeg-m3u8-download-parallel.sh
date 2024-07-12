#!/bin/bash

# download all m3u8's in the current dir.

outdir="/storage/TV Shows/Rigo/"

# parallel -q needed to allow quoted paths.
find . -name \*.m3u8 -print0 | parallel -j8 -0 -q \
ffmpeg -loglevel warning -stats -protocol_whitelist file,http,https,tcp,tls -allowed_extensions ALL -i {} -bsf:a aac_adtstoasc -c copy "$outdir{/.}.mp4"