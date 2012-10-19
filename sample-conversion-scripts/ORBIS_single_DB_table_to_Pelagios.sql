-- run this to a file, then replace |n and |t with newlines and tabs respectively
SELECT
    '<rdf:Description rdf:ID="' || objectid || '">|n' ||
    '|t<rdf:type rdf:resource="http://www.openannotation.org/ns/Annotation"/>|n' ||
    '|t<oac:hasBody rdf:resource="http://pleiades.stoa.org' || "PLPATH" || '"/>|n' ||
    '|t<oac:hasTarget rdf:resource="http://orbis.stanford.edu/api/site/'||objectid||'"/>|n' ||
    '|t<dcterms:creator rdf:resource="http://orbis.stanford.edu/"/>|n' ||
    '|t<dcterms:title>The Roman era place, '||label||'</dcterms:title>|n' ||
    '</rdf:Description>' AS rdfrecord 
FROM o_sites WHERE "PLPATH" LIKE '/places%' ORDER BY objectid;
