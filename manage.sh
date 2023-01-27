#!/bin/bash

if [ -v $1 ];then
  ACTION="build"
else
  ACTION=$@
fi

docker run \
  -it \
  -p 1313:1313 \
  --mount type=bind,source="$(pwd)",target=/src \
  --mount type=bind,source="$(pwd)"/public,target=/public \
  --rm \
  klakegg/hugo:ext-alpine $ACTION
