---
title: "Summer of Code"
date: 2020-02-02T21:14:19-05:00
layout: page
cover: "images/gsoc_banner.png"
smallCover: true
---

# Google Summer of Code - Ideas List

Welcome to the OpenEMR project!

OpenEMR is a open source EMR (electronic medical record) software suite primarily developed in PHP, MySQL/MariaDB, Javascript, CSS, and HTML.

Below is a list of project ideas (click on the project title to go to the Forum thread).

## [FHIR Integration](https://community.open-emr.org/t/project-fhir-integration/13701)

Fast Healthcare Interoperability Resources (FHIR) is a standard for exchanging healthcare information electronically. Goal is to integrate a FHIR server and client with OpenEMR to allow importing and exporting of patient data.

* Mentors: Stephen Nielson, Jerry Padgett
* Tags: New Feature


## [Standardized Patient Data](https://community.open-emr.org/t/project-standardized-patient-data/13702)

Goal is to develop a mechanism to create and import large datasets of standardized patient data. This is a high impact project that would then markedly improve instructional use of OpenEMR and markedly improve OpenEMR's use in the data analytics field.

* Mentors: Robert Down, Brady Miller
* Tags: New Feature 


## [Quality Reporting](https://community.open-emr.org/t/project-quality-reporting/13703)

Quality reporting and collection of clinical metrics are a valuable tool for clinics. It allows self-assessment, quality improvement projects, and reporting of metrics to regulation entities. Goal is to integrate quality reporting into OpenEMR. Note this project will involve both choosing of which clinical metrics to assess and then developing these in OpenEMR.

* Mentors: Stephen Waite, Brady Miller
* Tags: New Feature


## [Upgrade Smarty2 to Smarty3](https://community.open-emr.org/t/project-upgrade-smarty2-to-smarty3/13704)

A minor, albeit critical part of the codebase uses Smarty. The goal of this project is to migrate this code from using Smarty version 2 to Smarty version 3. This is critical for "future proofing" of OpenEMR's codebase since Smarty version 2 will be deprecated in the future.

* Mentors: Brady Miller, Roberto Vasquez
* Tags: Modernization 


## [Standardize PDF Tools](https://community.open-emr.org/t/project-standardize-pdf-tools/13705)

OpenEMR currently uses several different PDF tools and libraries, which complicates code development. Goal is to standardize all PDF output from a common PDF library.

* Mentors: Brady Miller, Jerry Padgett
* Tags: Optimization 


## [PACS Server Integration](https://community.open-emr.org/t/project-pacs-server-integration/13706)

Picture Archiving and Communication System (PACS) is a system that allows storing and viewing of patient imaging, such as Xrays, CT scans, and ultrasounds. Goal is it integrate a PACS server with OpenEMR to allow the viewing and storage of patient imaging.

* Mentors: Brady Miller, Asher Densmore-Lynn, Stephen Waite, Victor Kofia
* Tags: New Feature 


## [Upgrade Codebase to Use Services](https://community.open-emr.org/t/project-upgrade-codebase-to-use-services/13707)

Since OpenEMR began in 2002, there are parts of the codebase that are crying out for modernization. The goal of this project is to lead a modernization effort that focuses on modern use of classes and services.

* Mentors: Sherwin Gaddis, Jerry Padgett, Brady Miller, Stephen Nielson, Tyler Wrenn
* Tags: Modernization 


## [Support MAR](https://community.open-emr.org/t/project-support-mar/13708)

A Medication Administration Record (MAR) is a record of all medications that are given to a patient while they are in a hospital or nursing home facility. OpenEMR currently supports a medication list and writing prescriptions, however, OpenEMR does not support a MAR. Goal is to implement a MAR in OpenEMR.

* Mentors: Robert Down, Stephen Waite
* Tags: New Feature 


## [Integrate Telehealth](https://community.open-emr.org/t/project-integrate-telehealth/13709)

Telehealth is increasingly being used in healthcare and while OpenEMR does support a patient portal and communication with physicians via secure messaging or chat, OpenEMR does not yet support Telehealth. The goal is to support telehealth in OpenEMR.

* Mentors: Jerry Padgett, Stephen Nielson
* Tags: New Feature 


## [Modernize Styling and User Interface](https://community.open-emr.org/t/project-modernize-styling-and-user-interface/13777)

OpenEMR recently completed a [very large code refactor to support Bootstrap 4 (BS4)](https://github.com/openemr/openemr/pull/2832). There is still much to do including replacing as much CSS/SASS with BS4 as possible, removing unneeded CSS/SASS, decreasing reliance on the !important tag, migrating the navbar to BS4, migrating inline stylesheets to external stylesheets, improving responsive behavior of the gui, ironing out bugs in the current themes, and improving the current themes and the overall gui experience.

* Mentors: Tyler Wrenn, Robert Down
* Tags: Modernization


## [Automated Testing](https://community.open-emr.org/t/project-automated-testing/13778)

OpenEMR currently has a initial framework for automated testing which uses travis to run testing on all PHP versions, all MySQL versions, and all MariaDB versions. What is missing is the actual tests. Currently there are a couple unit tests and a couple e2e (functional) tests and no api tests. We are waiting for somebody to come along to pick and implement testing frameworks and design extensive automated unit, functional, and api testing.

* Mentors: Brady Miller, (additional mentor(s) pending)
* Tags: Modernization


## [Modernize Database](https://community.open-emr.org/t/project-modernize-database/13829)

The OpenEMR database has been waiting patiently for a student to modernize it. At this time, OpenEMR overrides the sql_mode settings (sets it to empty) in order to ensure compatibility with MariaDB and MySQL and issues are beginning to arise because of this. Goal would be for OpenEMR's database to support the default sql_mode settings in [MariaDB](https://mariadb.com/kb/en/sql-mode/) and [MySQL8](https://dev.mysql.com/doc/refman/8.0/en/sql-mode.html) (note mysql8 has more by default). Goal of this modernization is to also support it for folks that are upgrading OpenEMR from prior versions. Another thing to consider is support for utf8mb4 encoding. After completing this, the student would then be in a good position to plan and begin development on a mechanism to support offsite use of OpenEMR; see here for thoughts and even a prelim PR on this using UUID's: https://github.com/openemr/openemr/pull/2360 .

* Mentors: Brady Miller, Jerry Padgett
* Tags: Modernization


## Custom proposal

The community is also very open to custom proposals. Check out the [OpenEMR Project Roadmap](https://www.open-emr.org/wiki/index.php/Roadmaps#OpenEMR_Project_Roadmap) and [Issue in Github](https://github.com/openemr/openemr/issues) for some more ideas, and highly recommend discussing your ideas on the [OpenEMR forum](https://community.open-emr.org/) or [OpenEMR chat](https://www.open-emr.org/chat) and/or contacting a mentor directly. 


## Mentors

* [Asher Densmore-Lynn](https://github.com/jesdynf) 
* [Brady Miller](https://github.com/bradymiller) 
* [Jerry Padgett](https://github.com/sjpadgett)
* [Roberto Vasquez](https://github.com/robertogagliotta) 
* [Robert Down](https://github.com/robertdown) 
* [Rod Roark](https://github.com/sunsetsystems)
* [Sherwin Gaddis](https://github.com/juggernautsei)
* [Stephen Nielson](https://github.com/adunsulag)
* [Stephen Waite](https://github.com/stephenwaite)
* [Tyler Wrenn](https://github.com/tywrenn)
* [Victor Kofia](https://github.com/kofiav)

## Organization Administrators

* [Brady Miller](https://github.com/bradymiller)
* [Jerry Padgett](https://github.com/sjpadgett)
* [Rod Roark](https://github.com/sunsetsystems)
* [Stephen Waite](https://github.com/stephenwaite)
