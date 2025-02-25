#!/usr/bin/env bash

# Created by Qoliber
#
# @author Lukasz Owczarczuk <lowczarczuk@qoliber.com>

# Dumping DB with Qoliber data trimming
mkdir -p var/dump
dumpCmd=$(php lib/n98-magerun2/modules/qoliber-magerun/bin/n98-magerun2.phar db:dump --strip="@qoliber" --no-tablespaces -f var/dump/db.sql --only-command)
dumpCmd=$(echo "$dumpCmd" | sed 's/mysqldump --single-transaction/mysqldump --single-transaction/')
eval "$dumpCmd"
if grep -q "utf8mb3_0900_ai_ci" "var/dump/db.sql"; then
  sed -i 's/utf8mb3_0900_ai_ci/utf8_general_ci/g' var/dump/db.sql
  sed -i 's/CHARSET=utf8mb3/CHARSET=utf8/g' var/dump/db.sql
fi
if grep -q "utf8mb4_0900_ai_ci" "var/dump/db.sql"; then
  sed -i 's/utf8mb4_0900_ai_ci/utf8mb4_general_ci/g' var/dump/db.sql
fi
rm -f var/dump/db.tar.gz
tar -czf var/dump/db.tar.gz -C var/dump db.sql
rm -f var/dump/db.sql
