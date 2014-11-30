# Business Processes - Icinga Web 2 module

If you want to visualize and monitor hierarchical business processes based on any or all objects monitored by Icinga, the Icinga Web 2 business process module is the way to go.

Want to create custom process-based dashboards? Trigger notifications at process or sub-process level? Provide a quick top-level view for thousands of components on a single screen? That's what this module has been designed for!

You're running a huge cloud, want to get rid of the monitoring noise triggered by your auto-scaling platform but still want to have detailled information just a couple of clicks away in case you need them? You will love this littled addon!

# Installation

Like with any other Icinga Web 2 module just drop me to one of your module folders and enable the `businessprocess` module in your web frontend or on CLI. Of course the `monitoring` module needs to be enabled and that's it, we have no farther dependencies.

# History

The Business Process module is based on the ideas of the Nagios(tm) Business Process Addon written by Bernd Strößenreuther and available at http://bp-addon.monitoringexchange.org/. We always loved it's simplicity and while it looks pretty oldschool right now there are still many shops happily using it in production.

## Compatibiliy

We fully support the BPaddon config language and will continue to do so. It's also perfectly valid to run both products in parallel based on the very same config files.

However, lot's of changes went on and are still going on under the hood. We have more features and new language constructs. We separated the config reader from the state fetcher in our code base. This will allow us to optionally support config backends like SQL databases. They are not faster than plain old text files, but they make it much easier to distribute configuration in a large environment.

## Improvements

### Speed

This module is definitively faster than the BPaddon used to be. No need for IDO caching or similar.


TODO: business impact of a specific object

