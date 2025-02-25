#!/usr/bin/env bash

# Created by Qoliber
#
# @author Lukasz Owczarczuk <lowczarczuk@qoliber.com>

# Removing DB dump from dump
mkdir -p var/dump && rm -f var/dump/db.tar.gz
