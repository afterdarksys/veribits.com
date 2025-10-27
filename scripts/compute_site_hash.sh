#!/bin/bash
# When we update the site, lets compute a site hash
#

_build="/Users/ryan/development/veribits.com";
_hashes="${_build}/.hashes";

test -d ${_build} && cd ${_build}
	_site_hash=$(find . -type f 2>/dev/null |openssl sha256);
	echo "${_site_hash}" >> ${_hashes}
	current_hash=$(cat <${_hashes} | head -1);
	echo "$current_hash";
