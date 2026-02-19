<?php
/**
 * Function Whitelist for PHP Smart Contract Sandbox
 * 
 * This file defines the list of allowed functions that can be executed
 * within the sandbox. All other PHP functions will be disabled.
 * 
 * This whitelist is used by:
 * - sandbox_bootstrap.php (runtime enforcement)
 * - scripts/generate_sandbox_config.php (generates disable_functions list and INI files)
 * 
 * To add a new function to the whitelist:
 * 1. Add it to the appropriate category below
 * 2. Run: php scripts/generate_sandbox_config.php
 * 3. This will regenerate disable_functions_list.txt and both INI files
 */

return [
    // FILE OPERATIONS (for bootstrap only - reading input file)
    'file_exists','is_readable','file_get_contents',
    
    // STRINGS
    'addslashes','base64_decode','base64_encode','chunk_split','htmlspecialchars','htmlentities',
    'html_entity_decode','implode','explode','join','lcfirst','ucfirst',
    'levenshtein','ltrim','rtrim','trim','md5','nl2br','quoted_printable_decode',
    'quoted_printable_encode','sha1','similar_text','soundex','str_getcsv',
    'str_ireplace','str_pad','str_repeat','str_replace','str_rot13','str_split',
    'str_word_count','strcasecmp','strcmp','stripslashes','strip_tags',
    'stripcslashes','stripos','strlen','strpos','strtolower','strtoupper',
    'substr','wordwrap',

    // ARRAYS
    'array_change_key_case','array_chunk','array_column','array_combine',
    'array_count_values','array_diff','array_diff_assoc','array_diff_key',
    'array_fill','array_fill_keys','array_flip','array_intersect',
    'array_intersect_assoc','array_intersect_key','array_keys','array_map',
    'array_merge','array_merge_recursive','array_multisort','array_pad',
    'array_pop','array_product','array_push','array_reduce','array_replace',
    'array_replace_recursive','array_reverse','array_search','array_shift',
    'array_slice','array_splice','array_sum','array_udiff','array_uintersect',
    'array_unique','array_unshift','array_values','array_walk','arsort','asort',
    'count','current','end','in_array','key','ksort','krsort','natcasesort',
    'natsort','next','pos','prev','range','reset','rsort','sizeof','sort',
    'uasort','uksort','usort',

    // MATH
    'abs','acos','acosh','asin','asinh','atan','atan2','atanh','base_convert',
    'bindec','ceil','cos','cosh','decbin','dechex','decoct','deg2rad','exp',
    'expm1','floor','fmod','hexdec','hypot','intdiv','log','log10','log1p',
    'max','min','octdec','pi','pow','rad2deg','round','sin','sinh','sqrt',
    'tan','tanh',

    // JSON
    'json_encode','json_decode',

    // BCMATH
    'bcadd','bcsub','bcmul','bcdiv','bcmod','bcpow','bcpowmod','bcsqrt',
    'bccomp','bcscale',

    // HASH
    'hash','hash_algos','hash_hmac','hash_hmac_algos','hash_pbkdf2',

    // REGEX
    'preg_match','preg_match_all','preg_split','preg_quote',

    // DATE PARSING (deterministic only - no current time access)
    // Note: strtotime removed - can be non-deterministic when used without args or with 'now'
    'date_parse','checkdate',
    
    // TYPE CHECKING (safe and deterministic)
    'is_array','empty','is_string','is_int','is_integer','is_numeric','is_null',
    'is_bool','is_object','is_scalar','is_callable','is_countable','is_iterable',
    'is_float','is_double','is_long','is_finite','is_infinite','is_nan',
    'gettype','array_key_exists','method_exists',
    
    // TYPE CONVERSION (safe and deterministic)
    'intval','floatval','doubleval','boolval','strval',

    //ADDITIONAL
    'version_compare', 'define', 'dirname', 'class_exists', 'pathinfo', 'basename',

    //SC FUNCTIONS:
    'key_exists','ord','print_r',

    //FOR example ERC-20 TOKEN - todo -check
    'gmp_init', 'gmp_mul', 'gmp_add', 'gmp_cmp', 'gmp_div_qr', 'pack', 'gmp_intval', 'bin2hex',

    //To supress warnings and other errors?
    'error_reporting','ob_start', 'ob_clean'
];

