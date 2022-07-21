# Monitoring

## Process Check Command

The module provides a CLI command to check a business process.

### Usage

General: `icingacli businessprocess process check <process> [options]`

Options:

```
  --config <configname>   Name of the config that contains <process>
  --details               Show problem details as a tree
  --colors                Show colored output
  --state-type <type>     Define which state type to look at. Could be either soft
                          or hard, overrides an eventually configured default
  --blame                 Show problem details as a tree reduced to the nodes
                          which have the same state as the business process
  --root-cause            Used in combination with --blame. Only shows
                          the path of the nodes which are responsible
                          for the state of the business process
  --downtime-is-ok        Treat hosts/services in downtime always as UP/OK.
  --ack-is-ok             Treat acknowledged hosts/services always as UP/OK.
```

### Detail View Integration

It is possible to show the monitored process in the service detail view.

For this to work, the name of the checkcommand configured in Icinga 2 must either
be `icingacli-businessprocess` or the name that can be configured in the module
configuration:

**/etc/icingaweb2/modules/businessprocess/config.ini**
```ini
[DetailviewExtension]
checkcommand_name=businessprocess-check
```

A service can define specific custom variables for this. Mandatory ones
that are not defined, cause the detail view integration to not be active.

| Variable Name                        | Mandatory | Description                                  |
|--------------------------------------|-----------|----------------------------------------------|
| icingacli\_businessprocess\_process  | Yes       | The `<process>` being checked                |
| icingacli\_businessprocess\_config   | No        | Name of the config that contains `<process>` |
| icingaweb\_businessprocess\_as\_tree | No        | Whether to show `<process>` as tree or tiles |
