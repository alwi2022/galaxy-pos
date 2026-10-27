<?php

return [
    'enabled' => (bool)getenv('GALAXY_DESKTOP_DATA'),
    'data_path' => getenv('GALAXY_DESKTOP_DATA') ?: null,
];
