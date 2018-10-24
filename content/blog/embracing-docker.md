---
title: Embracing Docker
author:
  name: Matthew Vita
  twitter: MatthewVita
date: '2018-01-22'
slug: embracing-docker
aliases:
    - /blog/post/embracing-docker
images:
  - images/embracing_docker_thumb.jpg
coverImage: images/embracing_docker_thumb.jpg
coverImageStyle: full
---
OpenEMR remains in the midst of its Docker revolution with big plans for the future, including utilizing docker images to support medical record interoperability with Fast Healthcare Interoperability Resources, patient data analytics with Shiny R, and patient imaging picture archiving and communication system integration with an Orthanc Digital Imaging and Communications in Medicine (DICOM) server.
<!--more-->

“Developers get so excited over Docker,” said [Matthew Vita](https://twitter.com/MatthewVita), a software developer and project administrator for OpenEMR. “By building, testing, and deploying software in Docker containers, no one has to bother with manually installing and configuring complex system dependencies for their operating system. This is a great innovation for OpenEMR developers, testers, and the clinicians we serve.”

[OpenEMR](http://open-emr.org), the world’s leading open-source EMR, has offered users various ways to install the platform since its conception in 2002. Notable examples include [standard Apache](http://www.open-emr.org/wiki/index.php/OpenEMR_Downloads) and [virtual machine appliance](http://www.open-emr.org/wiki/index.php/OpenEMR_Downloads#Appliance) LAMP architecture for IT-minded folks, a [Windows-friendly XAMPP package](http://www.open-emr.org/wiki/index.php/OpenEMR_Downloads#Windows:_Pre-installed_OpenEMR_with_the_XAMPP_Package), and a [Linux Debian package](http://www.open-emr.org/wiki/index.php/OpenEMR_Downloads#Ubuntu_.2F_Mint_.2F_Debian). Our developers have made it a point to use the best packaging and deployment solutions available, but still found themselves answering emails and forum tickets around the management and troubleshooting of such tools.

In mid-2017, a few developers in our community started to play around with [Docker](https://hub.docker.com/r/openemr/openemr/) with varied success. The concept of Docker being “an open platform for developers and sysadmins to build, ship, and run distributed applications, whether on laptops, data center VMs, or the cloud” sounded too good to be true, but the entire technology industry seemed to be betting on it, so our experimentation continued. “After a few months of research and testing, our developers became very comfortable with spinning up the server, document store, and database with compose, pushing images to our hub, and supporting OpenEMR containers on the cloud.”, said Dr. Brady Miller, an OpenEMR project administrator and physician.

“To really touch on how much this technology has made our lives easier, consider that all OpenEMR community demo and QA servers are running on the cloud in Docker containers. We can simply take an in-development feature and present it to our developers and clinicians for review with only a few commands”, said Jason Oettinger, an OpenEMR contributor and incoming medical student.

While [other install options are still available from SourceForge](https://sourceforge.net/projects/openemr/), which are still downloaded 5,000 times per month, our community is encouraging [Docker](https://hub.docker.com/r/openemr/openemr/) for new deployments, whether on-premise or using one of our freely-available cloud packages.

OpenEMR remains in the midst of its Docker revolution with big plans for the future, including utilizing docker images to support medical record interoperability with Fast Healthcare Interoperability Resources FHIR (FHIR), patient data analytics with Shiny R, and patient imaging picture archiving and communication system (PACS) integration with an Orthanc Digital Imaging and Communications in Medicine (DICOM) server.

Duc Tran contributed to this blog post.
