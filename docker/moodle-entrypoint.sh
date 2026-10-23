#!/bin/sh
set -eu

CONFIG_DIR="/var/www/moodle-config"
CONFIG_FILE="${CONFIG_DIR}/config.php"
LEGACY_CONFIG=""
MOODLE_ROOT="/var/www/html"
MOODLEDATA="/var/moodledata"

mkdir -p "${CONFIG_DIR}" "${MOODLEDATA}"
chown root:root "${CONFIG_DIR}"
chmod 0700 "${CONFIG_DIR}"

# First deployment only: preserve the existing site's config.php from the old
# named volume. Never run a new installer or overwrite an existing config.
# if [ ! -s "${CONFIG_FILE}" ]; then
#     if [ -s "${LEGACY_CONFIG}" ]; then
#         cp "${LEGACY_CONFIG}" "${CONFIG_FILE}"
#         echo "Imported config.php from the legacy Moodle code volume."
#     else
#         echo >&2 "ERROR: ${CONFIG_FILE} is empty and no legacy config.php was found at ${LEGACY_CONFIG}."
#         echo >&2 "Set MOODLE_LEGACY_CODE_VOLUME_NAME to the current /var/www/html volume; do not start a fresh install against the production database."
#         exit 1
#     fi
# fi

# chown root:www-data "${CONFIG_FILE}"
# chmod 0640 "${CONFIG_FILE}"
# cp "${CONFIG_FILE}" "${MOODLE_ROOT}/config.php"
# chown root:www-data "${MOODLE_ROOT}/config.php"
# chmod 0640 "${MOODLE_ROOT}/config.php"

# # On first boot only, fix ownership for the new Alpine PHP-FPM www-data UID.
# # The marker lives in the private config volume, not in public web content.
# PERMISSIONS_MARKER="${CONFIG_DIR}/.moodledata-owner-fixed"
# if [ ! -f "${PERMISSIONS_MARKER}" ]; then
#     echo "Preparing Moodle data directory ownership (one-time operation)..."
#     chown -R www-data:www-data "${MOODLEDATA}"
#     chmod 2770 "${MOODLEDATA}"
#     touch "${PERMISSIONS_MARKER}"
# fi

exec "$@"
