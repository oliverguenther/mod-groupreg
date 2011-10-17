#!/usr/bin/perl
# 
# Schnittstelle zu Gruppenzuteilung und dem Choicegroup Plugin von Moodle
# Autor: Oliver Günther, 2011
#
#
# $ perl assign_groups.pl <groupregid>
# groupregid muss eine Integer-ID aus der Tabelle ${moodle_prefix}_choicegroup sein
# 
# Das Skript lädt die Auswahl aus mdl_choicegroup_answers und Gruppengröße aus mdl_choicegroup_options

use strict;
use warnings;
use DBI;

my $groupregid = shift || die "Keine groupregid angegeben. Kann keine Zuteilung durchführen";
my $dbname = shift || die "Moodle-Datenbank nicht angegeben.";
my $dbuser = shift || die "Moodle-Usernamen nicht angegeben.";
my $dbpass = shift || die "Moodle-Passwort nicht angegeben.";
my $mdl_prefix = shift || ''; # could be null

die "Choicegroup-ID darf nur ein ganzzahliger Wert sein. " unless ($groupregid =~ /\d+/);

# ----------------------------------------------------------------------------
# Lese Daten von mdl_choicegroup, user choicegroup hat hier nur SELECT-Rechte!

my $db = DBI->connect("DBI:mysql:$dbname;host=localhost", $dbuser, $dbpass)
		or die ("Kann keine Verbindung zur Datenbank aufbauen : " . $DBI::errstr . "\n");

# Gruppen-IDs und Gruppengrößen lesen
# %groups: ID -> Größe
my %groups;

# Gruppierungen zwischen äquivalenten Gruppen
# (z.B. zwei Montagsgruppen zur selben Zeit, unterschiedlicher Tutor)
# werden für Favoriten und Nieten zusammen ausgewählt

# <grouping_id> -> @groupids
my %groupings;

# groupid -> grouping_id
my %assoc_grouping;


{
my $result = $db->selectall_arrayref("SELECT id, grouping, maxanswers FROM ${mdl_prefix}groupreg_options WHERE groupregid='$groupregid'");
foreach my $group (@$result) {
	my $groupid = $$group[0];
	my $grouping_id = $$group[1];
	my $size = $$group[2];
	
	$groups{$groupid} = $size;
	if ($grouping_id) {	
		push(@{$groupings{$grouping_id}}, $groupid);
		$assoc_grouping{$groupid} = $grouping_id;
	}	
}
}

# Gruppenanmeldung von Studenten (Jede Anmeldung ist eine Gruppe, mind also 1 User pro Gruppe)
# %usergroups => {ugrp1 => (user1, user2), ugrp2 => (user3, user4) ...}
my %usergroups;

# Antworten der Gruppe lesen
# %auswahl => {ugrp1 => {1 => Präferenz1, 2=> Präferenz2}, ... }
my %auswahl;

# Nieten der Gruppe lesen
# %nieten => {ugrp1 => (Gruppe1, Gruppe2) ...}
my %nieten;
{
my $result = $db->selectall_arrayref("SELECT userid, usergroup, optionid, preference FROM ${mdl_prefix}groupreg_answers WHERE groupregid='$groupregid' ORDER BY preference");
foreach my $group (@$result) {
	my $userid = $$group[0];
	my $usergroupid = $$group[1];
	my $optionid = $$group[2];
	my $preference = $$group[3];

	${$usergroups{$usergroupid}}{$userid} = 1;
	
	if ($preference) {
		${$auswahl{$usergroupid}}{$preference} = $optionid;
	} else {
		# Nieten haben keine Präferenz
		push(@{$nieten{$usergroupid}}, $optionid);
		if ($assoc_grouping{$optionid}) {
			push(@{$nieten{$usergroupid}}, $_) foreach (@{$groupings{$assoc_grouping{$optionid}}});
		}
	}
}
}

$db->disconnect();


# ----------------------------------------------------------------------------
# Daten bereitstellen für distributor.jar

mkdir("/tmp/groupreg/") unless (-d "/tmp/groupreg/");

open(GROUPS, ">" , "/tmp/groupreg/${groupregid}_groups");
open(STUDENTS, ">" , "/tmp/groupreg/${groupregid}_students");

# Gruppen schreiben, pro Zeile Eintrag als "Gruppen-ID Größe"
print GROUPS "$_ $groups{$_}\n" foreach keys %groups;


# Studenten schreiben, drei Zeilen pro Gruppe
# 1. Zeile Infos: Gruppenname StudentenID StudentenID
# 2. Zeile Auswahlen: Gruppen-ID Gruppen-ID
# 3. Zeile Nieten: Gruppen-ID Gruppen-ID
foreach my $usergroupid (keys %usergroups) {
	
	# Gruppen ausgeben
	print STUDENTS "GROUPID$usergroupid";
	foreach my $user (keys %{$usergroups{$usergroupid}}) {
		print STUDENTS " $user";
	}
	print STUDENTS "\n";
	
	# Auswahl pro Gruppe ausgeben, inklusive aller Groupings
	# Bereits ausgegebene Gruppen werden ignoriert (z.b. wenn zwei Gruppen als unterschiedliche Prefs gewählt werden, die in einem Grouping sind)
	my %seen;
	my %selected;
	my @output;
	foreach my $pref (sort keys %{$auswahl{$usergroupid}}) {
		my $pref_group = ${$auswahl{$usergroupid}}{$pref}; 
		if ($assoc_grouping{$pref_group}) {
			# alle äquvialenten
			my @equiv_groups = @{$groupings{$assoc_grouping{$pref_group}}};
			foreach (@equiv_groups) {
				(push(@output, $_) && $seen{$_}++) unless $seen{$_};
			}
		} else {
			(push(@output, $pref_group) && $seen{$pref_group}++) unless $seen{$pref_group};
		}
	}
	print STUDENTS join(" ", @output);
	print STUDENTS "\n";

	# Nieten ausgeben (wenn existent)
	if ($nieten{$usergroupid}) {
		my @nieten = keys %{{ map { $_ => 1 } @{$nieten{$usergroupid}}}};
		print STUDENTS join(" ", @nieten) , "\n";
	} else {
		print STUDENTS "\n";
	}
	
}

close(GROUPS);
close(STUDENTS);

# ----------------------------------------------------------------------------
# Zuteilung mit distributor.jar
system("/usr/bin/java -jar group-assign/distributor.jar -i -p -d 2 /tmp/groupreg/${groupregid}_groups /tmp/groupreg/${groupregid}_students > /tmp/groupreg/${groupregid}_results");

open(RESULTS, "<" , "/tmp/groupreg/${groupregid}_results");
my @results = <RESULTS>;
die "Keine Ergebnisse erhalten " unless scalar(@results);

# %zuteilung : userid -> assigned_group
my %zuteilung;

foreach my $line (@results) {
	my ($studentid, $groupid, $choice) = ($line =~ /(\w+) (\w+) (\w+)/);
	die ("Konnte Zeile des Ergebnis nicht auslesen") unless ($studentid && $groupid);
	
	# choice wird hier ignoriert. ggf. relevant für Ausgabe?
	$zuteilung{$studentid} = $groupid;
	
}

print "Student $_ wurde zur Gruppe $zuteilung{$_} zugewiesen! \n" foreach keys %zuteilung;

# ----------------------------------------------------------------------------
# In DB schreiben

$db = DBI->connect("DBI:mysql:$dbname;host=localhost", $dbuser, $dbpass)
		or die ("Kann keine Verbindung zur Datenbank aufbauen : " . $DBI::errstr . "\n");


# Bisherige evtl. existierende Zuteilungen für groupregid löschen
my $count = $db->do("DELETE FROM ${mdl_prefix}groupreg_assigned where groupregid=$groupregid");

# Neue Zuteilungen speichern
foreach my $studentid (keys %zuteilung) {
	$db->do("INSERT INTO ${mdl_prefix}groupreg_assigned (groupregid, userid, optionid) 
			VALUES($groupregid, $studentid, $zuteilung{$studentid})");
}

$db->disconnect();


# ----------------------------------------------------------------------------
# Aufräumen

`rm /tmp/groupreg/${groupregid}_groups`;
`rm /tmp/groupreg/${groupregid}_students`;
`rm /tmp/groupreg/${groupregid}_results`;
