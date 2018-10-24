---
title: "OpenEMR Offers a Panel of Turn-Key Solutions with Amazon's Cloud Services"
date: '2018-06-01'
slug: openemr-offers-panel-of-turn-key-solutions-with-amazons-cloud-services
aliases:
    - /blog/post/openemr-offers-panel-of-turn-key-solutions-with-amazons-cloud-services
images:
  - images/OpenEMR_Cloud_AWS_Offerings.png
coverImage: images/OpenEMR_Cloud_AWS_Offerings.png
---
OpenEMR now offers a full panel of easy to install packages on Amazon’s Cloud Services platform, AWS.
<!--more-->

[OpenEMR](https://www.open-emr.org), the most popular open-source electronic health record and medical practice management solution, now offers a [full panel of easy to install packages](https://www.open-emr.org/wiki/index.php/AWS_Cloud_Packages_Comparison) on Amazon’s Cloud Services.

In 2017, OpenEMR released its first Amazon Cloud Services offering, [OpenEMR Cloud Full Stack](https://www.open-emr.org/blog/post/openemr-announces-initial-availability-of-openemr-cloud-on-aws/), with the goal of enabling Enterprise use of OpenEMR.  However many clinics and academic settings did not require the complexities of a large-scale full cloud offering and/or HIPAA eligibility.  The OpenEMR community realized that a one size fits all Amazon cloud service approach could not address the vast range of differing requirements and workflows in modern day clinical and academic settings.

In response, the OpenEMR community rolled out a panel of Amazon cloud service offerings covering the wide range of clinical and academic settings, from low-resource clinics in remote areas to large enterprise multispeciality facilities. These offerings were developed and tested by a team of volunteers in the OpenEMR community that included physicians, nurses, scientists, and software and cloud engineers. This team included Dr. Andre Millet, Dr. Brady Miller, Daniel Ehrlich, Jason Oettinger, Matthew Vita, Robert Down, Stephen Waite, and OpenEMR's Chief Systems Architect, Asher Densmore-Lynn.

"OpenEMR stands alone," Densmore-Lynn said, "in its embrace of containerization and orchestration technology. OpenEMR is the first open-source EMR in the Amazon Marketplace, and the only one aggressively leveraging Amazon's proprietary encryption technology in the pursuit of HIPAA compliance and domestic use." He continues, "Our range of cloud offerings including two host-it-yourself options you can run on your desktop or on your existing server hardware are designed to accommodate live clinical use, classroom instruction, or even something as modest as hassle-free demonstration and development."

This panel of OpenEMR Cloud offerings allows any user to take advantage of the benefits of cloud services, which include:

- Cutting-edge network security. The security measures leverage the Amazon Web Services data centers, built around superior physical, network, and software security. 
- Zero capital outlay and predictable expenses. AWS services are billed hourly, and you only pay for what you use. You need not purchase physical hardware (or to replace it when it breaks), and you can conduct (and conclude) experiments in scaling resources up and down with the push of a button.
- Easy software deployments. With several simple steps, administrators can have a cloud optimized solution for OpenEMR up and running in the cloud. If customizations are needed, developers can test and deploy the changes in a development environment with ease.
- Robust backup and recovery solutions. Automated backups, disaster recovery, and server redundancy are commonplace in the cloud and can be fine tuned to suit each clinic’s specific needs. 

Something that makes OpenEMR's cloud offerings unique is the lack of reliance on any particular cloud. Because all of the options are built on similar orchestration frameworks, data can be backed up on one offering and recovered to another. This means that if internet access goes down for a prolonged period of time, such as in [Puerto Rico after hurricane Maria](https://www.open-emr.org/blog/post/hurricane-maria-puerto-rico-openemr-success/), an OpenEMR cloud instance with all its data can be packaged up and delivered by the supporting vendor to run locally on a server in the clinic.

There are five different OpenEMR Amazon Cloud Service offerings ranging in costs from $10 per month to $100+ per month in Amazon fees. The offerings are Shared Hosting, Express, Express Plus, Standard, and Full Stack. All of the offerings share the availability of automated backups, low maintenance and support for SSL encryption. An updated comparison chart of these offerings is available on the [OpenEMR website](https://www.open-emr.org/wiki/index.php/AWS_Cloud_Packages_Comparison) , and each [OpenEMR Cloud offering](https://www.open-emr.org/wiki/index.php/OpenEMR_Downloads#AWS_Cloud) is described below:

- Shared Hosting. This is the most inexpensive OpenEMR cloud service offering and is geared to areas with very low resources that do not require HIPAA compliance. It is based on the Lightsail Amazon service, which has the goal of low expense and easy installation. It is not HIPAA eligible and opportunities for further integration with other Amazon services are limited.
- Express. This is a very inexpensive OpenEMR cloud service offering that is available on the AWS Marketplace and is geared to areas with low resources that do not require HIPAA compliance or demonstration and education purposes. It is based wholly on the standard Amazon Elastic Compute Cloud (EC2) service and is not HIPAA eligible. Deployment directly from the Amazon Marketplace allows an administrator to get started with the product in minutes.
- Express Plus. This is an inexpensive OpenEMR cloud service offering that follows the Express single-instance model, but takes advantage of AWS CloudFormation orchestration to pursue HIPAA eligibility, with features like off-instance backups, data encryption in transit and at rest, CloudTrail's audit tracing, and vertical scaling options at the touch of a button.
- Standard. This is an OpenEMR cloud service offering that is geared to clinics that require HIPAA compliance. Standard adds Amazon Relational Database Service (RDS) to allow a redundant, managed database. Standard is HIPAA eligible, and adds powerful automated recovery features and further vertical scaling options to the faultless Marketplace deployment pathway.
- Full Stack. This is an OpenEMR cloud service offering that is geared to clinics that require a highly available, performant solution with HIPAA compliance. Full Stack supplies auto-scaling web-workers, redundant stores for patient records and documents, field-testable backups, and a multi-AZ networking configuration that permits normal operations even tested against the loss of an Amazon Availability Zone.

Professional support is provided by vendors like [ACE Health Solutions](https://www.open-emr.org/wiki/index.php/Professional_Support#ACE_Health_Solutions) and [Juggernaut Systems Express](https://www.open-emr.org/wiki/index.php/Professional_Support#Juggernaut_Systems_Express), and the OpenEMR community stands behind both practicing and academic users with support in its forums and online chat.

“These offerings are just the start as OpenEMR leverages cloud services for deploying OpenEMR to both small and large clinical and academic settings,” Dr. Brady Miller, an OpenEMR project administrator and physician, said, “although these offerings will drastically improve deployments of OpenEMR, these are truly just building blocks as more related cloud services are integrated to facility interoperability, quality assurance, performance reporting, education, research, academics, data analysis and disaster response.”
