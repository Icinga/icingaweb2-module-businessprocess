# Collection of ideas

Filter "appserver" = host=*-rz-app-* & service=*jmx*

Filter "appserver kunde1" = Filter["appserver"] & _host_customer = kunde1

Process "kunde1" = 1 of: Filter["appserver kunde1"]->group('hostname')

Customer App * => host_name = customer-app-* => service_description = lx-jmx-* | service_description = lx-procs tomcat

Customer App = 1 of: Audi App *
