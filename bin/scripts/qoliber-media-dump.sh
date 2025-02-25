#!/usr/bin/env bash

# Created by Qoliber
#
# @author Lukasz Owczarczuk <lowczarczuk@qoliber.com>

mediaDir='pub/media'
resolvedDir=$(readlink -f "$mediaDir")

if [ ! -L "$mediaDir" ]; then
  resolvedDir="$mediaDir"
fi

if [ ! -d "$resolvedDir" ]; then
  echo "Error: '$resolvedDir' does not exist"
  exit 1
fi

dumpDir='var/dump'
archiveName="$dumpDir/media.tgz"

mkdir -p "$dumpDir"
rm -f "$archiveName"

tar --exclude='*cache*' --exclude='*-bak' --exclude='*_bak' --exclude='*_backup' \
    --exclude='*-backup' --exclude='*_tmp' --exclude='*.tmp' --exclude='*_old' \
    --exclude='*.old' --exclude='*-old' --exclude='amfeed' --exclude='sitemap' \
    --exclude='tmp' --exclude='.[^/]*' --exclude='css' --exclude='css_secure' \
    --exclude='js' --exclude='test' --exclude='js_secure' --exclude='import' \
    --exclude='*.sql' --exclude='*.log' --exclude='*.tar' --exclude='*.gz' \
    --exclude='*.zip' --exclude='catalog/catalog' --exclude='catalog/product/product' \
    --exclude='catalog/product/product.' --exclude='ftp-upload' --exclude='importexport' \
    --exclude='pdf' --exclude='csv' --dereference --no-same-owner \
    -zcvf "$archiveName" -C "$(dirname "$resolvedDir")" "$(basename "$resolvedDir")"
