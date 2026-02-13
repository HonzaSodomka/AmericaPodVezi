#!/bin/bash
# Download Tailwind CSS CLI (Standalone executable)
curl -sLO https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-linux-x64
chmod +x tailwindcss-linux-x64
mv tailwindcss-linux-x64 tailwindcss

# Build the CSS file
./tailwindcss -i ./input.css -o ./output.css --minify

echo "Build complete: output.css generated."