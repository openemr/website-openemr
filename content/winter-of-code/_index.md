---
title: "Winter of Code"
date: 2020-02-02T21:14:19-05:00
layout: page
---

# <center>[Winter of Code](https://gdsc.winterofcode.tech/)</center>

# Overview

Welcome to the OpenEMR project!

OpenEMR is a open source EMR (electronic medical record) software suite primarily developed in PHP, MySQL/MariaDB, Javascript, CSS, and HTML.

OpenEMR also has an API with FHIR and SMART on FHIR support that uses OAUTH2 for authentication. So, any app stack that supports this can be used for app development.

**Step 1.** To get started, recommend participating in the community and having fun ([Github](https://github.com/openemr), [Forum](https://community.open-emr.org/), [Chat](https://www.open-emr.org/chat)). Beginners, intermediate, and advanced developers are all welcome. We have a course to get anybody up and running as quick as possible at article ["You Can Be a OpenEMR Developer in 5 Easy Steps"](https://www.open-emr.org/blog/you-can-be-a-openemr-developer-in-5-easy-steps/).

**Step 2.** Application process is pending. *We will list the application instructions here when available.*

---

# Project Ideas

## Mobile App

This is a very flexible project to design a new mobile app that integrates with OpenEMR API to solve a focused problem. OpenEMR has an API with FHIR and SMART on FHIR support that uses OAUTH2 for authentication. So, any app stack that supports this can be used for app development.

## Automated Testing

OpenEMR currently has a initial framework for automated testing which uses Github Actions to run testing on all PHP versions, all MySQL versions, and all MariaDB versions. Currently there are several unit tests, api test, e2e (functional) tests. We are waiting for somebody to come along and markedly expand the automated testing.

## Modernize Database

The OpenEMR database has been waiting patiently for a student to modernize it. At this time, OpenEMR overrides the sql_mode settings (sets it to empty) in order to ensure compatibility with MariaDB and MySQL and issues are beginning to arise because of this. Goal would be for OpenEMR's database to support the default sql_mode settings in [MariaDB](https://mariadb.com/kb/en/sql-mode/) and [MySQL8](https://dev.mysql.com/doc/refman/8.0/en/sql-mode.html) (note mysql8 has more by default). Goal of this modernization is to also support it for folks that are upgrading OpenEMR from prior versions.

## Standardize PDF Tools

OpenEMR currently uses several different PDF tools and libraries, which complicates code development. Goal is to standardize all PDF output from a common PDF library.

## PACS Server Integration

Picture Archiving and Communication System (PACS) is a system that allows storing and viewing of patient imaging, such as Xrays, CT scans, and ultrasounds. Goal is it integrate a PACS server with OpenEMR to allow the viewing and storage of patient imaging.

## Custom proposal

The community is also very open to custom proposals. Check out the [OpenEMR Project Roadmap](https://www.open-emr.org/wiki/index.php/Roadmaps#OpenEMR_Project_Roadmap) and [Issue in Github](https://github.com/openemr/openemr/issues) for some more ideas, and highly recommend discussing your ideas on the [OpenEMR forum](https://community.open-emr.org/) or [OpenEMR chat](https://www.open-emr.org/chat) and/or contacting a mentor directly.


# Mentors
(In progress of recruiting mentors (there will likely be about 5)

* [Brady Miller](https://github.com/bradymiller)
* [Robert Down](https://github.com/robertdown)
* [Stephen Waite](https://github.com/stephenwaite)
<!-- * [Arnab Naha](https://github.com/arnabnaha) -->
<!-- * [Asher Densmore-Lynn](https://github.com/jesdynf) -->
<!-- * [Daniel Pflieger](https://github.com/growlingflea) -->
<!-- * [David Vu](https://community.open-emr.org/u/david.vu) -->
<!-- * [Dixon Whitmire](https://github.com/dixonwhitmire) -->
<!-- * [Jerry Padgett](https://github.com/sjpadgett) -->
<!-- * [Julie Buurman](https://github.com/boxlady) -->
<!-- * [Ken Chapple](https://github.com/kchapple) -->
<!-- * [Nilesh Hake](https://community.open-emr.org/u/nilesh_hake) -->
<!-- * [Rachel Ellison](https://community.open-emr.org/u/rachel_ellison) -->
<!-- * [Roberto Vasquez](https://github.com/robertogagliotta) -->
<!-- * [Robert Hausam](https://community.open-emr.org/u/rhausam) -->
<!-- * [Rod Roark](https://github.com/sunsetsystems) -->
<!-- * [Sandra Gutierrez](https://github.com/gutiersa) -->
<!-- * [Sherwin Gaddis](https://github.com/juggernautsei) -->
<!-- * [Tyler Wrenn](https://github.com/tywrenn) -->
<!-- * [Victor Kofia](https://github.com/kofiav) -->

# Organization Administrators

* [Brady Miller](https://github.com/bradymiller)
* [Stephen Waite](https://github.com/stephenwaite)
<!-- * [Jerry Padgett](https://github.com/sjpadgett) -->
<!-- * [Rod Roark](https://github.com/sunsetsystems) -->

