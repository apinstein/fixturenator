<?php

$spec = Pearfarm_PackageSpec::create(array(Pearfarm_PackageSpec::OPT_BASEDIR => dirname(__FILE__)))
             ->setName('fixturenator')
             ->setChannel('apinstein.pearfarm.org')
             ->setSummary('A factory-based fixture generator. Inspired by http://github.com/thoughtbot/factory_girl but thoughtfully ported to PHP.')
             ->setDescription('AWESOME.')
             ->setReleaseVersion('0.0.3')
             ->setReleaseStability('alpha')
             ->setApiVersion('0.0.3')
             ->setApiStability('alpha')
             ->setLicense(Pearfarm_PackageSpec::LICENSE_MIT)
             ->setNotes('http://github.com/apinstein/fixturenator')
             ->addMaintainer('lead', 'Alan Pinstein', 'apinstein', 'apinstein@mac.com')
             ->addFilesSimple('Fixturenator.php')
             ;
