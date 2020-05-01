#!/usr/bin/env bash
CWD_BASENAME=${PWD##*/}

FILES+=("${CWD_BASENAME}.php")
FILES+=("LICENSE.txt")
FILES+=("logo.png")

MODULE_VERSION="$(sed -ne "s/\\\$this->version *= *['\"]\([^'\"]*\)['\"] *;.*/\1/p" ${CWD_BASENAME}.php)"
MODULE_VERSION_FILE=`echo ${MODULE_VERSION} | sed -e "s/\./_/g"`;
MODULE_VERSION=${MODULE_VERSION//[[:space:]]}
ZIP_FILE="${CWD_BASENAME}-${MODULE_VERSION_FILE}.zip"

echo "Going to zip ${CWD_BASENAME} version ${MODULE_VERSION}"

cd ..
rm -f ${ZIP_FILE};

for E in "${FILES[@]}"; do
  find ${CWD_BASENAME}/${E}  -type f -exec zip -9 ${ZIP_FILE} {} \;
done
