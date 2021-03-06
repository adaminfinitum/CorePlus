/**
 * Provides some string manipulations that can be used throughout.
 */


(function(){
	
	// This is an array of mappings from international characters and accent marks to the latin counter parts.
	var internationalmappings = {
		'À': 'A',
		'Á': 'A',
		'Â': 'A',
		'Ã': 'A',
		'Ä': 'A',
		'Å': 'AA',
		'Æ': 'AE',
		'Ç': 'C',
		'È': 'E',
		'É': 'E',
		'Ê': 'E',
		'Ë': 'E',
		'Ì': 'I',
		'Í': 'I',
		'Î': 'I',
		'Ï': 'I',
		'Ð': 'D',
		'Ł': 'L',
		'Ñ': 'N',
		'Ò': 'O',
		'Ó': 'O',
		'Ô': 'O',
		'Õ': 'O',
		'Ö': 'O',
		'Ø': 'OE',
		'Ù': 'U',
		'Ú': 'U',
		'Ü': 'U',
		'Û': 'U',
		'Ý': 'Y',
		'Þ': 'Th',
		'ß': 'ss',
		'à': 'a',
		'á': 'a',
		'â': 'a',
		'ã': 'a',
		'ä': 'a',
		'å': 'aa',
		'æ': 'ae',
		'ç': 'c',
		'è': 'e',
		'é': 'e',
		'ê': 'e',
		'ë': 'e',
		'ì': 'i',
		'í': 'i',
		'î': 'i',
		'ï': 'i',
		'ð': 'd',
		'ł': 'l',
		'ñ': 'n',
		'ń': 'n',
		'ò': 'o',
		'ó': 'o',
		'ô': 'o',
		'õ': 'o',
		'ō': 'o',
		'ö': 'o',
		'ø': 'oe',
		'ś': 's',
		'ù': 'u',
		'ú': 'u',
		'û': 'u',
		'ū': 'u',
		'ü': 'u',
		'ý': 'y',
		'þ': 'th',
		'ÿ': 'y',
		'ż': 'z',
		'Œ': 'OE',
		'œ': 'oe',
		'&': 'and'
	};
	
	Core.Strings = {
		/**
		 * Convert a given string's internationalized characters to their latin counters.
		 * 
		 * @param string
		 * @return string
		 */
		toLatin: function(string){
			var i;

			for(i in internationalmappings){
				string = string.replace(new RegExp(i, 'g'), internationalmappings[i]);
			}
			return string;
		},
		
		/**
		 * Cleanup a string and ensure it can make a valid URL.
		 * 
		 * @param string
		 * @return string
		 */
		toURL: function(string){
			// URLs should only be in latin.
			string = Core.Strings.toLatin(string);

			// Spaces get replaced with a separator
			string = string.replace(/[ ]/g, '-');

			// Anything else I missed?  Get rid of it!
			string = string.replace(/[^a-z0-9\-]/ig, '');

			// Multiple separators should get truncated, along with beginning and trailing ones.
			string = string.replace(/[-]+/g, '-').replace(/^-/, '').replace(/-$/, '');

			// Multiple empty slashes should be truncated to a single one.
			string = string.replace(/\/[\/]*/g, '/');

			// And lowercase it.
			string = string.toLowerCase();

			return string;
		},
		
		/**
		 * Trim off whitespace from a given string.
		 * This is required because IE does not natively support trim() :/
		 * 
		 * @param string
		 * @return string
		 */
		trim: function(string){
			return string.replace(/^[\s]*/gm, '').replace(/[\s]*$/gm, '');
		},

		rot13: function (str) {
			return str.replace(/[a-zA-Z]/g, function(c) {
				return String.fromCharCode((c <= 'Z' ? 90 : 122) >= (c = c.charCodeAt(0) + 13) ? c : c - 26);
			});
		},

		/**
		 * Very simple templating system that takes a string and replaces %1, %2, etc with that parameter.
		 * This system also supports an Object for the second parameter.  This will perform named replaces.
		 *
		 * Example:
		 * simple_template('Something [%%1%%] goes [%%2%%]', 'red', 'over there');
		 * // returns 'Something red goes over there'
		 *
		 * Example:
		 * simple_template('something [%%color%%] goes [%%location%%]', { color: 'blue', location: 'up here' });
		 * // returns 'something blue goes up here'
		 *
		 * @return string
		 */
		template: function(){
			var template, args = [], i = 0;

			if(arguments.length < 2){
				throw 'Please provide at least two arguments to simple_format!';
			}

			template = arguments[0];

			// If an object is sent in for the second argument and there are exactly two, then a name replace should be used.
			if(arguments.length == 2 && arguments[1] instanceof Object){
				for(i in arguments[1]){
					template = template.replace('[%%' + i + '%%]', arguments[1][i]);
				}
				return template;
			}

			for(i=0; i<arguments.length; i++){
				if(i == 0) continue;
				template = template.replace('[%%' + i + '%%]', arguments[i]);
			}

			return template;
		}
	};
	
	String.prototype.toLatin = function(){
		return Core.Strings.toLatin(this.toString());
	};
	
	String.prototype.toURL = function(){
		return Core.Strings.toURL(this.toString());
	};
	
	// Because this is already supported in everything except IE...
	if(typeof String.prototype.trim != 'function'){
		String.prototype.trim = function(){
			return Core.Strings.trim(this.toString());
		}
	}

	String.prototype.template = function(replacements){
		var args = [this.toString()], i;
		for(i in arguments){
			args.push(arguments[i]);
		}

		return Core.Strings.template.apply(this, args);
	}
})();
