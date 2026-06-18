# Computer Builder Data Templates

These JSON files are the default templates used when creating a `ComputerBuilderMapping`.
Each file corresponds to a section of the GLPI Inventory Format schema.

Fields containing `{{ ldap.attributeName }}` are replaced at sync time with the value
of the corresponding LDAP attribute. Empty fields (`""`) are stripped from the final
inventory payload and can be filled in with an LDAP placeholder or a static value.

## bios.json

Reference: [inventory.schema.json — bios](https://github.com/glpi-project/inventory_format/blob/89f177aa93595bdff01c3f670e32edd11ace0524/inventory.schema.json#L166-L247)

| Field | Description | Notes |
|-------|-------------|-------|
| `ssn` | System serial number | |
| `smanufacturer` | System manufacturer | |
| `smodel` | System model | |
| `assettag` | Asset tag | |
| `bdate` | BIOS release date | Format: `dateordatetime` (e.g. `2023-10-15`) |
| `bmanufacturer` | BIOS manufacturer | |
| `bversion` | BIOS version | |
| `mmanufacturer` | Motherboard manufacturer | |
| `mmodel` | Motherboard model | |
| `msn` | Motherboard serial number | |
| `skunumber` | SKU number | |
| `biosserial` | BIOS serial number | |
| `enclosureserial` | Chassis serial number | |
| `secure_boot` | Secure boot status | Accepted values: `enabled`, `disabled`, `unsupported` |

## operatingsystem.json

Reference: [inventory.schema.json — operatingsystem](https://github.com/glpi-project/inventory_format/blob/89f177aa93595bdff01c3f670e32edd11ace0524/inventory.schema.json#L1312-L1436)

| Field | Description | Notes |
|-------|-------------|-------|
| `name` | OS distributor name | |
| `version` | OS release version | |
| `arch` | Processor architecture | e.g. `x86_64` |
| `boot_time` | Last boot timestamp | Format: `datetime` |
| `dns_domain` | DNS domain | |
| `fqdn` | Fully qualified domain name | |
| `full_name` | Full OS description | e.g. `Fedora release 25 (Twenty Five)` |
| `hostid` | Unique host identifier | |
| `install_date` | OS installation date | Format: `dateordatetime` |
| `kernel_name` | Kernel type | e.g. `linux` |
| `kernel_version` | Kernel version | e.g. `4.11.3-200.fc25.x86_64` |
| `ssh_key` | SSH public key | |
| `service_pack` | Service pack version | |

> **Note:** The `timezone` field (object with `name` and `offset`) is intentionally
> omitted from the default template. It can be added manually in the mapping if needed,
> but both `name` and `offset` must be set — a partial timezone object is invalid.
