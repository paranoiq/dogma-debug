<?php

namespace Dogma\Debug;

class MysqlResultInfo
{

    public const FLAG_NOT_NULL = 1; // Field can't be NULL
    public const FLAG_PRI_KEY = 2; // Field is part of a primary key
    public const FLAG_UNIQUE_KEY = 4; // Field is part of a unique key
    public const FLAG_MULTIPLE_KEY = 8; // Field is part of a key
    public const FLAG_BLOB = 16; // Field is a blob
    public const FLAG_UNSIGNED = 32; // Field is unsigned
    public const FLAG_ZEROFILL = 64; // Field is zerofill
    public const FLAG_BINARY = 128; // Field is binary
    public const FLAG_ENUM = 256; // field is an enum
    public const FLAG_AUTO_INCREMENT = 512; // field is a autoincrement field
    public const FLAG_TIMESTAMP = 1024; // Field is a timestamp
    public const FLAG_SET = 2048; // field is a set
    public const FLAG_NO_DEFAULT_VALUE = 4096; // Field doesn't have default value
    public const FLAG_ON_UPDATE_NOW = 8192; // Field is set to NOW on UPDATE
    public const FLAG_NUMERIC = 32768; // Field is num (for clients)
    public const FLAG_PART_KEY = 16384; // Intern; Part of some key
    public const FLAG_GROUP = 32768; // Intern: Group field
    public const FLAG_UNIQUE = 65536; // Intern: Used by sql_yacc
    public const FLAG_BINCMP = 131072; // Intern: Used by sql_yacc
    public const FLAG_GET_FIXED_FIELDS = 262144; // Used to get fields in item tree
    public const FLAG_FIELD_IN_PART_FUNC = 524288; // Field part of partition func

    public const TYPE_DECIMAL = 0;
    public const TYPE_TINY = 1;
    public const TYPE_SHORT = 2;
    public const TYPE_LONG = 3;
    public const TYPE_FLOAT = 4;
    public const TYPE_DOUBLE = 5;
    public const TYPE_NULL = 6;
    public const TYPE_TIMESTAMP = 7;
    public const TYPE_LONGLONG = 8;
    public const TYPE_INT24 = 9;
    public const TYPE_DATE = 10;
    public const TYPE_TIME = 11;
    public const TYPE_DATETIME = 12;
    public const TYPE_YEAR = 13;
    public const TYPE_NEWDATE = 14; /**< Internal to MySQL. Not used in protocol */
    public const TYPE_VARCHAR = 15;
    public const TYPE_BIT = 16;
    public const TYPE_TIMESTAMP2 = 17;
    public const TYPE_DATETIME2 = 18; /**< Internal to MySQL. Not used in protocol */
    public const TYPE_TIME2 = 19; /**< Internal to MySQL. Not used in protocol */
    public const TYPE_TYPED_ARRAY = 20; /**< Used for replication only */
    public const TYPE_INVALID = 243;
    public const TYPE_BOOL = 244; /**< Currently just a placeholder */
    public const TYPE_JSON = 245;
    public const TYPE_NEWDECIMAL = 246;
    public const TYPE_ENUM = 247;
    public const TYPE_SET = 248;
    public const TYPE_TINY_BLOB = 249;
    public const TYPE_MEDIUM_BLOB = 250;
    public const TYPE_LONG_BLOB = 251;
    public const TYPE_BLOB = 252;
    public const TYPE_VAR_STRING = 253;
    public const TYPE_STRING = 254;
    public const TYPE_GEOMETRY = 255;

    public const FLAGS = [
        self::FLAG_NOT_NULL => 'NOT_NULL',
        self::FLAG_PRI_KEY => 'PRI_KEY',
        self::FLAG_UNIQUE_KEY => 'UNIQUE_KEY',
        self::FLAG_MULTIPLE_KEY => 'MULTIPLE_KEY',
        self::FLAG_BLOB => 'BLOB',
        self::FLAG_UNSIGNED => 'UNSIGNED',
        self::FLAG_ZEROFILL => 'ZEROFILL',
        self::FLAG_BINARY => 'BINARY',
        self::FLAG_ENUM => 'ENUM',
        self::FLAG_AUTO_INCREMENT => 'AUTO_INCREMENT',
        self::FLAG_TIMESTAMP => 'TIMESTAMP',
        self::FLAG_SET => 'SET',
        self::FLAG_NO_DEFAULT_VALUE => 'NO_DEFAULT_VALUE',
        self::FLAG_ON_UPDATE_NOW => 'ON_UPDATE_NOW',
        self::FLAG_NUMERIC => 'NUMERIC',
        self::FLAG_PART_KEY => 'PART_KEY',
        //self::FLAG_GROUP => 'GROUP',
        self::FLAG_UNIQUE => 'UNIQUE',
        self::FLAG_BINCMP => 'BINCMP',
        self::FLAG_GET_FIXED_FIELDS => 'GET_FIXED_FIELDS',
        self::FLAG_FIELD_IN_PART_FUNC => 'FIELD_IN_PART_FUNC',
    ];

    public const DATA_TYPES = [
        self::TYPE_DECIMAL => 'DECIMAL',
        self::TYPE_TINY => 'TINY',
        self::TYPE_SHORT => 'SHORT',
        self::TYPE_LONG => 'LONG',
        self::TYPE_FLOAT => 'FLOAT',
        self::TYPE_DOUBLE => 'DOUBLE',
        self::TYPE_NULL => 'NULL',
        self::TYPE_TIMESTAMP => 'TIMESTAMP',
        self::TYPE_LONGLONG => 'LONGLONG',
        self::TYPE_INT24 => 'INT24',
        self::TYPE_DATE => 'DATE',
        self::TYPE_TIME => 'TIME',
        self::TYPE_DATETIME => 'DATETIME',
        self::TYPE_YEAR => 'YEAR',
        self::TYPE_NEWDATE => 'NEWDATE',
        self::TYPE_VARCHAR => 'VARCHAR',
        self::TYPE_BIT => 'BIT',
        self::TYPE_TIMESTAMP2 => 'TIMESTAMP2',
        self::TYPE_DATETIME2 => 'DATETIME2',
        self::TYPE_TIME2 => 'TIME2',
        self::TYPE_TYPED_ARRAY => 'TYPED_ARRAY',
        self::TYPE_INVALID => 'INVALID',
        self::TYPE_BOOL => 'BOOL',
        self::TYPE_JSON => 'JSON',
        self::TYPE_NEWDECIMAL => 'NEWDECIMAL',
        self::TYPE_ENUM => 'ENUM',
        self::TYPE_SET => 'SET',
        self::TYPE_TINY_BLOB => 'TINY_BLOB',
        self::TYPE_MEDIUM_BLOB => 'MEDIUM_BLOB',
        self::TYPE_LONG_BLOB => 'LONG_BLOB',
        self::TYPE_BLOB => 'BLOB',
        self::TYPE_VAR_STRING => 'VAR_STRING',
        self::TYPE_STRING => 'STRING',
        self::TYPE_GEOMETRY => 'GEOMETRY',
    ];

    public const CHARACTER_SETS = [
        1 => 'BIG5',
        3 => 'DEC8',
        4 => 'CP850',
        6 => 'HP8',
        7 => 'KOI8R',
        8 => 'LATIN1',
        9 => 'LATIN2',
        10 => 'SWE7',
        11 => 'ASCII',
        12 => 'UJIS',
        13 => 'SJIS',
        16 => 'HEBREW',
        18 => 'TIS620',
        19 => 'EUCKR',
        22 => 'KOI8U',
        24 => 'GB2312',
        25 => 'GREEK',
        26 => 'CP1250',
        28 => 'GBK',
        30 => 'LATIN5',
        32 => 'ARMSCII8',
        33 => 'UTF8',
        35 => 'UCS2',
        36 => 'CP866',
        37 => 'KEYBCS2',
        38 => 'MACCE',
        39 => 'MACROMAN',
        40 => 'CP852',
        41 => 'LATIN7',
        51 => 'CP1251',
        54 => 'UTF16',
        56 => 'UTF16LE',
        57 => 'CP1256',
        59 => 'CP1257',
        60 => 'UTF32',
        63 => 'BINARY',
        92 => 'GEOSTD8',
        95 => 'CP932',
        97 => 'EUCJPMS',
        248 => 'GB18030',
        255 => 'UTF8MB4',
    ];

}