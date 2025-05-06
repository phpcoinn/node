<?php

$chain_id = trim(@file_get_contents(dirname(__DIR__)."/chain_id"));
if($chain_id != DEFAULT_CHAIN_ID && file_exists(__DIR__ . "/checkpoints.".$chain_id.".php")) {
    require_once __DIR__ . "/checkpoints.".$chain_id.".php";
    return;
}

$checkpoints = [
    1 => "9aeKHauMRjJC6GzXvjWpYfYcEA4vaFQLxDWitpVDReMQ",
    100000 => "CdZWtDjL1gR8Trmr7AmZzr6NvWaL4YqU7fvMBi5aDvig",
    200000 => "5SmJjMLujd1eMupCxjPW7pfWaSvEpA78ucyytG7NZWSX",
    300000 => "AuEMBrteJwXDj7aQvGGGZwV4m9w9ZRsYUfVBvfv1rhVk",
    400000 => "8huPL5RfSRTJPE1TUbnnf6jGnodSrkPSu34mebsz9hKr",
    500000 => "6Zc6pnn3bxstSBpV5Lr1dmwuTio3o76mLgCezpbmqHza",
    510000 => "HLwuPjxbbrhKkuwm5qWwv27Eu4npyxgBDvtAYCF1ZgAp",
    520000 => "6dYENVA64uVfv932RpvDtyjyzwfFK7U9DyxNQUtQ3ec7",
    530000 => "57QRa2m3peKmvbutPLGSfHXySX1tnzouMJJii6UKDH4X",
    540000 => "8xV48JMZm6MU4qpYHq77WFGAUZNVpiZdxNcLNHqsLGNZ",
    550000 => "41PG7tfGRCCSHwMoE5pkiNqn5nF4PXX1wYAuqap8MWBk",
    560000 => "7mY7Gnx1eksLKE6FngSarjqpDQEta9o8uYqTwgB9VmjY",
    561000 => "Gm1ZQU7qjwiZWX7q5QHZMkA5SPXuY9coeJeZVb5XjTsn",
    562000 => "KMCes9LK16fcnmBtKufssYRSuMm9vFhSp1xGqsoUFrn",
    563000 => "6EatKBVRkyqTu2cqJHJBcy3aYPdPHE5iEFCFxdzhZdpA",
    564000 => "25kLGoY4xd6o72DKTXA77wmdZYAto1pcTk1rw2pK7EQm",
    564811 => "BpaUVoE7degr96VWhPYwKciTPt6bAFJ1ymcNpjp4Dxkr",
    565000 => "FE1sfL3r6YN5z5nHpUimFpobiZ8jGRfykN6fmtCTehy5",
    566000 => "4RBYtqeYDoafzjKaNv3Am8Byrka89xeVVHNdvkHLj9vB",
    567000 => "8XCsgmRnWF2uYSiGLaB8zE1k8NvXwQ3bJXBufH7SFsS6",
    568000 => "XiwvDKLh86NAng7eFKa3bWyyKnb9aqtX9MwtmrNYdNj",
    569000 => "5q3SYXWUxjmyJ5JK15dzBt5VKCyxuS6G1ECKPx9Hqdvd",
    570000 => "G9hKpwDtcbHfoFLJQZNN1AV6MZ91jeaXguToYfsSpzE4",
    571000 => "WGViLLXUjo9KEm8mRSAQpcopezW7G7azGYD22zsBEXR",
    572000 => "CM51q2ibqTB4PQHNyMXmUuqYi5ZkfG32QX5p87prgs8e",
    573000 => "841T8wQtQX8zfMy6CLLWHTo3D2YFCswDPBLxYFRJ9iLJ",
    574000 => "Fb8zJMtQbhV7dngp9uWgx6pxvRcqB72jLDMfudun13xm",
    575000 => "3rPDbatdTQkaYEyCaWEm4QvQMezN6zRu5gEXhtqqkSez",
    576000 => "Duyn2CvZRPWaTLCLbuUYkH399nKaQZuqbdirXB51g7pz",
    577000 => "HNWA8k5KdygAaptKDLGPw2cGF1GfSys3NdExpQ5cAKQe",
    578000 => "EGZuDHivPjSweZQv6sYS3s5gVAunt3kAXesdXM1swy4K",
    579000 => "8LhwEj2LocXvdGAaZ5wC54CsCcUwQZoqMPQZWZDppHds",
    580000 => "8husyNntSF3tmXM6DYscqVSh3utn8EPJNaa6qnLbcLA4",
    581000 => "3oDkX8kFv72sDTkkfbsusqcWHZkTNpQRJcfencAowQDT",
    582000 => "DRTgaSrCVAjcF6tPX4DWresXbC4vdxfyHMU2GBCksoiP",
    583000 => "5po2BDHCcTzXQ5D4fK3LzMa1xy3YELgqWcTcngjwdjS8",
    584000 => "7vBBdnVnjWSpyph1TM8jc8eRhkj9DhuF97jnH7Xeps5F",
    585000 => "EzhUQ7d9KWCoqLAkv2ssSwfQvnG1ZwMJvfKjedJZ6mjq",
    586000 => "Ho8HdCje79XgtbmA12PFKMAZ91m3bUZD5GmeGJJo2e4b",
    587000 => "Dm4ZMMogsE6dHX1ThWVAaAxz6XYu9M5yVVAwoD594cj1",
    588000 => "HDZFNBTAgV5PmGymV3fRN2hZDB8feteUHpNGwTLkBeri"
];
