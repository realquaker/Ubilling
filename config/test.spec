[sectionname]
LABEL="log recorder debug log"
OPTION=RECORDER_DEBUG
TYPE=TRIGGER
VALUES=""
PATTERN=""
VALIDATOR=""

[sectionname2]
LABEL="log rotator debug log"
OPTION=ROTATOR_DEBUG
TYPE=CHECKBOX
PATTERN=""
VALIDATOR=""

[sectionname3]
LABEL="MySQL dumps max age in days before rotation"
OPTION=BACKUPS_MAX_AGE
TYPE=SELECT
VALUES="1,2,3,4,5,6,7"
PATTERN=""
VALIDATOR=""
DEFAULT="7"

[sectionname4]
LABEL="some custom name"
OPTION=CUSTNAME
TYPE=TEXT
VALUES=""
PATTERN=""
VALIDATOR=""

[sectionname5]
LABEL="email here"
OPTION=EMAIL
TYPE=TEXT
VALUES=""
PATTERN="email"
VALIDATOR=""
DEFAULT="test@somedomain.com"

[sectionname6]
LABEL="Some MAC address"
OPTION=SOME_MAC
TYPE=TEXT
VALUES=""
PATTERN=""
VALIDATOR="IsMacValid"
DEFAULT=""