
# Dumper exceptions

Situations when dumper does not present data as ordinary dump

## Default and user defined type handlers

by default *Dumper* defines special handling for these types:
   - *BackedEnum*
   - *UnitEnum*
   - *mysqli*
   - *DateTimeInterface*

this behavior can be turned off by setting `$useFormatters` to `false`


## Hidden parts of the data structures

### Depth or length limit reached
- `...`

### Hidden fields
- `*****`

### Recursion
- `recursion`

### Do-not-traverse settings
- `skipped`

### Reference to previously dumped value
- `^ same`

### Unknown arguments
when dumping callstack with missing context (after out-of-memory error)
- `???`


## Decoded or transformed data

### Binary string formatted for screen (hexadec etc.)
- `binary:`

### Decoded Base64 string
- `base64:`

### Unserialized data
- `serialized:`

### Decompressed data
- `zip:`

### JSON string dumped as structure
- `json:`
