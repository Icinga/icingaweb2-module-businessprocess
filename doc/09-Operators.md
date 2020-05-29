# Operators <a id="operators">

Every Business Process requires an Operator. This operator defines its behaviour and specifies how its very own state is
going to be calculated.

## AND <a id="and-operator">

The `AND` operator selects the **WORST** state of its child nodes:

![And Operator](screenshot/09_operators/0901_and-operator.png)

## OR <a id="or-operator">

The `OR` operator selects the **BEST** state of its child nodes:

![Or Operator](screenshot/09_operators/0902_or-operator.png)

![Or Operator #2](screenshot/09_operators/0903_or-operator-without-ok.png)

## MIN n <a id="min-operator">

The `MIN` operator selects the **WORST** state out of the **BEST n** child node states:

![MIN](screenshot/09_operators/0904_min-operator.png)
