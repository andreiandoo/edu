jQuery(document).ready(function($) {
    // Load country, county, and city data from JSON
    $.getJSON(myconnector_params.jsonUrl, function(data) {
        var countries = data;

        var countrySelector = $('#country');
        var schoolSelector = $('#school');

        // Populate country selector
        var countryOptions = $.map(countries, function(country) {
            return {
                value: country.id,
                text: country.name
            };
        });

        countryOptions.sort(function(a, b) {
            return a.text.localeCompare(b.text);
        });

        // Add "Selecteaza tara" as the first option
        countryOptions.unshift({ value: '', text: 'Selecteaza tara' });

        // Clear the select field and append the sorted options
        countrySelector.empty().append($.map(countryOptions, function(option) {
            return $('<option>', option);
        }));

        countrySelector.change(function() {
            var selectedCountryId = $(this).val();
            var selectedCountryData = countries.find(country => country.id == selectedCountryId);
            var showCountyCitySelectors = [181, 85]; // Country IDs that show county and city selectors

            if (showCountyCitySelectors.includes(parseInt(selectedCountryId))) {
                // Show county and city selectors
                $('#county-row').show();
                $('#city-row').show();

                populateCountySelector($('#county'), selectedCountryData);
                populateCountySelector($('#school_county'), selectedCountryData);

                $('#city').empty();
                $('#school_city').empty();
            } else {
                // Hide county and city selectors
                $('#county-row').hide();
                $('#city-row').hide();
                $('#school-county-row').hide();
                $('#school-city-row').hide();
                $('#school-selector').hide();
                $('#school-location-selector').hide();

                $('#county').empty().append($('<option>', {
                    value: '',
                    text: 'Selecteaza judet'
                }));
                $('#city').empty().append($('<option>', {
                    value: '',
                    text: 'Selecteaza oras'
                }));
                $('#school_county').empty();
                $('#school_city').empty();
                $('#school').empty();
            }

            updateSchoolFieldsVisibility();
        });

        function updateSchoolFieldsVisibility() {
            var selectedCountryId = $('#country').val();
            var selectedAcademicStatus = $('#academic_status').val();
            console.log(selectedAcademicStatus);

            if (selectedCountryId === '181' && selectedAcademicStatus === 'Elev') { // if Romania and elev
                $('#school-selector').show();
                $('#school-location-selector').show();
                $('#school-row').show();
                $('#school-city-row').show();
                $('#school-county-row').show();
            } else {
                $('#school-selector').hide();
                $('#school-location-selector').hide();
                $('#school-row').hide();
                $('#school-city-row').hide();
                $('#school-county-row').hide();
            }
        }

        $('#county').change(function() {
            var selectedCountyName = $(this).val();
            var selectedCountryData = countries.find(country => country.id == $('#country').val());
            var selectedCountyData = selectedCountryData.states.find(county => county.name === selectedCountyName);

            populateCitySelector($('#city'), selectedCountyData);
            $('#school_county').val(selectedCountyName);
            $('#school_county').trigger('change');
        });

        $('#city').change(function() {
            var selectedCityName = $(this).val();
            var selectedCountryData = countries.find(country => country.id == $('#country').val());
            var selectedCountyName = $('#county').val();
            var selectedCountyData = selectedCountryData.states.find(county => county.name === selectedCountyName);

            var selectedCityData = selectedCountyData.cities.find(city => city.name === selectedCityName);
            $('#school_city').val(selectedCityName);
            populateSchoolSelector($('#school'), selectedCityData);
        });

        $('#school_county').change(function() {
            var selectedSchoolCounty = $(this).val();
            var selectedCountryData = countries.find(country => country.id == $('#country').val());
            var selectedCountyData = selectedCountryData.states.find(county => county.name === selectedSchoolCounty);

            populateCitySelector($('#school_city'), selectedCountyData);
            $('#school_city').trigger('change');
        });

        $('#school_city').change(function() {
            var selectedSchoolCity = $(this).val();
            var selectedCountryData = countries.find(country => country.id == $('#country').val());
            var selectedCountyName = $('#school_county').val();
            var selectedCountyData = selectedCountryData.states.find(county => county.name === selectedCountyName);

            var selectedCityData = selectedCountyData.cities.find(city => city.name === selectedSchoolCity);
            populateSchoolSelector($('#school'), selectedCityData);
        });

        function populateCountySelector(countySelector, selectedCountryData) {
            countySelector.empty().append($('<option>', {
                value: '',
                text: 'Selecteaza judet'
            }));

            var countyOptions = $.map(selectedCountryData.states, function(county) {
                return county.name;
            });

            countyOptions.sort(function(a, b) {
                return a.localeCompare(b);
            });

            $.each(countyOptions, function(index, countyName) {
                countySelector.append($('<option>', {
                    value: countyName,
                    text: countyName
                }));
            });
        }

        function populateCitySelector(citySelector, selectedCountyData) {
            citySelector.empty().append($('<option>', {
                value: '',
                text: 'Selecteaza oras'
            }));

            var cityOptions = $.map(selectedCountyData.cities, function(city) {
                return city.name;
            });

            cityOptions.sort(function(a, b) {
                return a.localeCompare(b);
            });

            $.each(cityOptions, function(index, cityName) {
                citySelector.append($('<option>', {
                    value: cityName,
                    text: cityName
                }));
            });
        }

        function populateSchoolSelector(schoolSelector, selectedCityData) {
            if (!schoolSelector || !selectedCityData || !selectedCityData.schools) {
                console.error('Invalid arguments passed to populateSchoolSelector');
                return;
            }
            schoolSelector.empty().append($('<option>', {
                value: '',
                text: 'Selecteaza scoala'
            }));

            var schoolOptions = $.map(selectedCityData.schools, function(school) {
                return school.name;
            });

            schoolOptions.sort(function(a, b) {
                return a.localeCompare(b);
            });

            $.each(schoolOptions, function(index, schoolName) {
                schoolSelector.append($('<option>', {
                    value: schoolName,
                    text: schoolName
                }));
            });
        }

        $('#academic_status').change(function() {
            updateSchoolFieldsVisibility();

            var selectedAcademicStatus = $(this).val();

            // Hide all sections initially and reset selected values
            $('#studentGrade').addClass('hidden').val('');
            $('#bachelorYear').addClass('hidden').val('');
            $('#masterYear').addClass('hidden').val('');

            // Show the appropriate section based on academic status
            if (selectedAcademicStatus === 'Elev') { //elev
                $('#studentGrade').removeClass('hidden');
            } else if (selectedAcademicStatus === 'Student licenta') { //student licenta
                $('#bachelorYear').removeClass('hidden');
            } else if (selectedAcademicStatus === 'Student master') { //student master
                $('#masterYear').removeClass('hidden');
            }
        });

        updateSchoolFieldsVisibility();
    });    
});