# Operators

Every Business Process requires an Operator. This operator defines its behaviour and specifies how its very own state is
going to be calculated.

## AND

The `AND` operator selects the **WORST** state of its child nodes:

![And Operator](screenshot/09_operators/0901_and-operator.png)

## OR

The `OR` operator selects the **BEST** state of its child nodes:

![Or Operator](screenshot/09_operators/0902_or-operator.png)

![Or Operator #2](screenshot/09_operators/0903_or-operator-without-ok.png)

## XOR

The `XOR` operator shows OK if only one of n children is OK at the same time. In all other cases the parent node is CRITICAL.
Useful for a service on n servers, only one of which may be running. If both were running,
race conditions and duplication of data could occur.

![Xor Operator](screenshot/09_operators/0906_xor-operator.png)

![Xor Operator #2](screenshot/09_operators/0907_xor-operator-not-ok.png)

## DEGRADED

The `DEGRADED` operator behaves like an `AND`, but if the resulting
state is **CRITICAL** it transforms it into a **WARNING**.
Refer to the table below for the case-by-case
analysis of the statuses.

![Degraded Operator](screenshot/09_operators/0905_deg-operator.jpg)

## MIN n

The `MIN` operator selects the **WORST** state out of the **BEST n** child node states:

![MIN](screenshot/09_operators/0904_min-operator.png)

## RATE n

The `RATE` operator selects the **WORST** state out of the **BEST n** rate child node states.
