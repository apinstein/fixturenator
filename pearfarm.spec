<?php

$spec = Pearfarm_PackageSpec::create(array(Pearfarm_PackageSpec::OPT_BASEDIR => dirname(__FILE__)))
             ->setName('fixturenator')
             ->setChannel('apinstein.pearfarm.org')
             ->setSummary('A factory-based fixture generator.')
             ->setDescription('AWESOME.')
             ->setReleaseVersion('0.0.2')
             ->setReleaseStability('alpha')
             ->setApiVersion('0.0.2')
             ->setApiStability('alpha')
             ->setLicense(Pearfarm_PackageSpec::LICENSE_MIT)
             ->setNotes('http://github.com/apinstein/fixturenator')
             ->addMaintainer('lead', 'Alan Pinstein', 'apinstein', 'apinstein@mac.com')
             ->addGitFiles()
             ;
