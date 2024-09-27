<?php

namespace Dogma\Debug;

class SideEffect
{

    // indirection
    public const READ_EXTERNAL_DATA = 1; // globals, static etc.
    public const WRITE_EXTERNAL_DATA = 2; // globals, static etc.
    public const MUTATE_ARGUMENT = 4;

    // hidden state
    public const GENERATORS = 8; // internal/static state, including random
    public const COROUTINES = 16;
    public const THREADS = 32;

    // modified execution & runtime modification
    public const USE_REFLECTION = 64;
    public const READ_VIA_REFLECTION = 128;
    public const WRITE_VIA_REFLECTION = 256;
    public const MODIFY_PRIMITIVES = 512; // change classes, functions etc.
    public const EVAL = 1024; // create_function() etc.
    public const SUBSYSTEM_HANDLERS = 2048; // error handlers, stream wrappers...
    public const INTERNALS = 4096; // garbage collection, caches etc.
    public const READ_CONFIG = 8192;
    public const WRITE_CONFIG = 16384;
    public const READ_ENV = 32768;
    public const WRITE_ENV = 65536;

    // error handling
    public const LOGGING = 1 << 16;
    public const RETURNS_ERROR = 1 << 17;
    public const THROWS = 1 << 18;

    // time
    public const READ_TIME = 1 << 19;
    public const SLEEP = 1 << 20;

    // output/input
    public const READ_INPUT = 1 << 21;
    public const WRITE_OUTPUT = 1 << 22;
    public const READ_HEADERS = 1 << 23;
    public const WRITE_HEADERS = 1 << 24;
    public const READ_SESSION = 1 << 25;
    public const WRITE_SESSION = 1 << 26;

    // local fs
    public const READ_FILESYSTEM = 1 << 27;
    public const WRITE_FILESYSTEM = 1 << 28;

    // external services
    public const READ_PROCESS_STATE = 1 << 29;
    public const EXECUTE_PROCESSES = 1 << 30;
    public const SERVICE_COMMUNICATION = 1 << 31; // DB, Redis...
    public const NETWORK_COMMUNICATION = 1 << 32; // uncategorized

    public const GUI = 1 << 33;

}
