#!/bin/bash
set -e
set -x

# Packages required by Liquidsoap
apt-get install -y --no-install-recommends libao-dev libasound2-dev libavcodec-dev libavdevice-dev libavfilter-dev libavformat-dev \
    libavutil-dev libfaad-dev libfdk-aac-dev libflac-dev libfreetype-dev libgd-dev libjack-dev \
    libjpeg-dev liblo-dev libmad0-dev libmagic-dev libmp3lame-dev libopus-dev libpng-dev libportaudio2 \
    libpulse-dev libsamplerate0-dev libsdl2-dev libsdl2-ttf-dev libsdl2-image-dev libshine-dev libsoundtouch-dev libspeex-dev \
    libsrt-dev libswresample-dev libswscale-dev libtag1-dev libtheora-dev libtiff-dev libx11-dev libxpm-dev \
    bubblewrap ffmpeg

# Optional audio plugins
apt-get install -y --no-install-recommends frei0r-plugins-dev ladspa-sdk multimedia-audio-plugins swh-plugins tap-plugins lsp-plugins-ladspa

# Per-architecture LS installs
ARCHITECTURE=amd64
ARM_FULL_BUILD="${ARM_FULL_BUILD:-false}"

if [[ "$(uname -m)" = "aarch64" && ${ARM_FULL_BUILD} == "false" ]]; then
    ARCHITECTURE=arm64
fi

# wget -O /tmp/liquidsoap.deb "https://github.com/savonet/liquidsoap/releases/download/v2.0.5/liquidsoap_2.0.5-ubuntu-focal-1_${ARCHITECTURE}.deb"
wget -O /tmp/liquidsoap.deb "https://github.com/savonet/liquidsoap-release-assets/releases/download/rolling-release-v2.1.x/liquidsoap-d5cf13e_2.1.0-ubuntu-focal-1_${ARCHITECTURE}.deb"

dpkg -i /tmp/liquidsoap.deb
apt-get install -y -f --no-install-recommends
rm -f /tmp/liquidsoap.deb
ln -s /usr/bin/liquidsoap /usr/local/bin/liquidsoap

# else
#    apt-get install -y --no-install-recommends build-essential libssl-dev libcurl4-openssl-dev m4 ocaml opam autoconf automake
#    sudo -u ambientcast bash /bd_build/stations/liquidsoap/build_as_ambientcast.sh
#    ln -s /var/ambientcast/.opam/4.13.1/bin/liquidsoap /usr/local/bin/liquidsoap
#    chmod a+x /usr/local/bin/liquidsoap
#     apt-get purge -y build-essential libssl-dev libcurl4-openssl-dev m4 ocaml opam autoconf automake
# fi
