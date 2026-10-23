#!/bin/sh
set -eu

CONFIG_DIR="/var/www/moodle-config"
CONFIG_FILE="${CONFIG_DIR}/config.php"
CURRENT_CONFIG="/var/www/current-moodle-code/config.php"
MOODLE_ROOT="/var/www/html"
MOODLEDATA="/var/moodledata"

mkdir -p "${CONFIG_DIR}" "${MOODLEDATA}"
chown root:root "${CONFIG_DIR}"
chmod 0700 "${CONFIG_DIR}"

# First deployment only: import config.php from the currently running Moodle 5.2.2
# code volume into a separate persistent volume. Do not run the installer.
if [ ! -s "${CONFIG_FILE}" ]; then
    if [ -s "${CURRENT_CONFIG}" ]; then
        cp "${CURRENT_CONFIG}" "${CONFIG_FILE}"
        echo "Imported config.php from the current Moodle 5.2.2 code volume."
    else
        echo >&2 "ERROR: ${CONFIG_FILE} is empty and no config.php was found at ${CURRENT_CONFIG}."
        echo >&2 "Set MOODLE_CURRENT_CODE_VOLUME_NAME to the current /var/www/html volume. Do not start a fresh install against the existing database."
        exit 1
    fi
fi

chown root:www-data "${CONFIG_FILE}"
chmod 0640 "${CONFIG_FILE}"
cp "${CONFIG_FILE}" "${MOODLE_ROOT}/config.php"
chown root:www-data "${MOODLE_ROOT}/config.php"
chmod 0640 "${MOODLE_ROOT}/config.php"

# The Moodledata volume is reused from the current site. Fix ownership once for
# the Alpine PHP-FPM www-data user; the marker survives normal restarts.
PERMISSIONS_MARKER="${CONFIG_DIR}/.moodledata-owner-fixed"
if [ ! -f "${PERMISSIONS_MARKER}" ]; then
    echo "Preparing ownership for the existing Moodledata volume (one-time operation)..."
    chown -R www-data:www-data "${MOODLEDATA}"
    chmod 2770 "${MOODLEDATA}"
    touch "${PERMISSIONS_MARKER}"
fi

exec "$@"
