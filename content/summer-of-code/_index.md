---
title: "Summer of Code"
date: 2020-02-02T21:14:19-05:00
layout: page
cover: "images/gsoc_logo.png"
smallCover: true
---

# Google Summer of Code - Ideas List

Welcome to the OpenEMR project!

OpenEMR is a open source EMR (electronic medical record) software suite primarily developed in PHP, MySQL/MariaDB, Javascript, CSS, and HTML.

---

**Below is a list of project ideas.**

## Standardized Patient Data

Goal is to develop a mechanism to create and import large datasets of standardized patient data. This is a high impact project that would then markedly improve instructional use of OpenEMR and markedly improve OpenEMR's use in the data analytics field.

* Tags: New Feature


## Upgrade Smarty2 to Smarty3

A minor, albeit critical part of the codebase uses Smarty. The goal of this project is to migrate this code from using Smarty version 2 to Smarty version 3. This is critical for "future proofing" of OpenEMR's codebase since Smarty version 2 will be deprecated in the future.

* Tags: Modernization


## Standardize PDF Tools

OpenEMR currently uses several different PDF tools and libraries, which complicates code development. Goal is to standardize all PDF output from a common PDF library.

* Tags: Optimization


## PACS Server Integration

Picture Archiving and Communication System (PACS) is a system that allows storing and viewing of patient imaging, such as Xrays, CT scans, and ultrasounds. Goal is it integrate a PACS server with OpenEMR to allow the viewing and storage of patient imaging.

* Tags: New Feature


## Support MAR

A Medication Administration Record (MAR) is a record of all medications that are given to a patient while they are in a hospital or nursing home facility. OpenEMR currently supports a medication list and writing prescriptions, however, OpenEMR does not support a MAR. Goal is to implement a MAR in OpenEMR.

* Tags: New Feature


## Integrate Telehealth

Telehealth is increasingly being used in healthcare and while OpenEMR does support a patient portal and communication with physicians via secure messaging or chat, OpenEMR does not yet support Telehealth. The goal is to support telehealth in OpenEMR.

* Tags: New Feature


## Automated Testing

OpenEMR currently has a initial framework for automated testing which uses Github Actions to run testing on all PHP versions, all MySQL versions, and all MariaDB versions. Currently there are several unit tests, api test, e2e (functional) tests. We are waiting for somebody to come along and markedly expand the automated testing.

* Tags: Modernization


## API Improvements

OpenEMR has an API, which is also the backbone of support for Fast Healthcare Interoperability Resources (FHIR) and SMART on FHIR applications. Goal is to improve the API and also increase breadth of support for SMART on FHIR applications.

* Tags: New Feature


## Modernize Database

The OpenEMR database has been waiting patiently for a student to modernize it. At this time, OpenEMR overrides the sql_mode settings (sets it to empty) in order to ensure compatibility with MariaDB and MySQL and issues are beginning to arise because of this. Goal would be for OpenEMR's database to support the default sql_mode settings in [MariaDB](https://mariadb.com/kb/en/sql-mode/) and [MySQL8](https://dev.mysql.com/doc/refman/8.0/en/sql-mode.html) (note mysql8 has more by default). Goal of this modernization is to also support it for folks that are upgrading OpenEMR from prior versions.

* Tags: Modernization


## Mobile App Improvements

OpenEMR currently has a mobile app based on Flutter that supports medicine recognition and patient searching. Several examples of improvements could include OAuth2 support and optimizing the medicine recognition feature and how it integrates with OpenEMR.

* Tags: Optimization


## New Mobile App

This is a very flexible project to design a new mobile app that integrates with OpenEMR API to solve a focused problem.

* Tags: New Feature


## Custom proposal

The community is also very open to custom proposals. Check out the [OpenEMR Project Roadmap](https://www.open-emr.org/wiki/index.php/Roadmaps#OpenEMR_Project_Roadmap) and [Issue in Github](https://github.com/openemr/openemr/issues) for some more ideas, and highly recommend discussing your ideas on the [OpenEMR forum](https://community.open-emr.org/) or [OpenEMR chat](https://www.open-emr.org/chat) and/or contacting a mentor directly. 


## Mentors

* [Arnab Naha](https://github.com/arnabnaha)
* [Asher Densmore-Lynn](https://github.com/jesdynf) 
* [Brady Miller](https://github.com/bradymiller)
* [Daniel Pflieger](https://github.com/growlingflea)
* [David Vu](https://community.open-emr.org/u/david.vu)
* [Dixon Whitmire](https://github.com/dixonwhitmire)
* [Jerry Padgett](https://github.com/sjpadgett)
* [Julie Buurman](https://github.com/boxlady)
* [Ken Chapple](https://github.com/kchapple)
* [Nilesh Hake](https://community.open-emr.org/u/nilesh_hake)
* [Rachel Ellison](https://community.open-emr.org/u/rachel_ellison)
* [Roberto Vasquez](https://github.com/robertogagliotta)
* [Robert Hausam](https://community.open-emr.org/u/rhausam)
* [Rod Roark](https://github.com/sunsetsystems)
* [Sandra Gutierrez](https://github.com/gutiersa)
* [Sherwin Gaddis](https://github.com/juggernautsei)
* [Stephen Waite](https://github.com/stephenwaite)
* [Tyler Wrenn](https://github.com/tywrenn)
* [Victor Kofia](https://github.com/kofiav)

## Organization Administrators

* [Brady Miller](https://github.com/bradymiller)
* [Jerry Padgett](https://github.com/sjpadgett)
* [Rod Roark](https://github.com/sunsetsystems)
* [Stephen Waite](https://github.com/stephenwaite)
