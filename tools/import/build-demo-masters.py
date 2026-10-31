#!/usr/bin/env python3
"""Build demo-masters.json, the galerio.net demo content manifest.

Facts about each object (title, date, medium, dimensions, accession number,
credit line, image and record URL) are read from the Metropolitan Museum of
Art's Open Access API (CC0 / public domain); the descriptions, profiles,
terms and collections are written for the demo and are editorial.

Usage: tools/import/build-demo-masters.py   (writes demo-masters.json beside it)
"""

import json
import os
import re
import time
import urllib.request

UA = 'galerio-demo-build (https://galerio.net)'
API = 'https://collectionapi.metmuseum.org/public/collection/v1/objects/%d'
HERE = os.path.dirname(os.path.abspath(__file__))
MET = 'The Metropolitan Museum of Art, New York'

# -- Terms ---------------------------------------------------------------

LABELS = [
	'Renaissance', 'Baroque', 'Dutch Golden Age', 'Rococo', 'Neoclassicism', 'Realism', 'Impressionism',
	'Post-Impressionism', 'Art Nouveau', 'Pictorialism', 'Early photography', 'Ukiyo-e', 'Ming dynasty',
	'Goryeo', 'Mughal', 'Deccan', 'Persian', 'Benin', 'Yoruba', 'Dogon', 'Asante', 'Moche', 'Maya',
	'Diné (Navajo)', 'Māori', 'Ancient Egyptian', 'Portraiture', 'Animal art',
]

MEDIUMS = [
	{'name': 'Painting'}, {'name': 'Oil', 'parent': 'Painting'}, {'name': 'Ink and watercolour', 'parent': 'Painting'},
	{'name': 'Manuscript illumination', 'parent': 'Painting'},
	{'name': 'Print'}, {'name': 'Woodblock', 'parent': 'Print'},
	{'name': 'Photography'}, {'name': 'Albumen print', 'parent': 'Photography'}, {'name': 'Platinum print', 'parent': 'Photography'},
	{'name': 'Sculpture'}, {'name': 'Bronze', 'parent': 'Sculpture'}, {'name': 'Brass', 'parent': 'Sculpture'},
	{'name': 'Stone', 'parent': 'Sculpture'}, {'name': 'Wood', 'parent': 'Sculpture'},
	{'name': 'Ceramics'}, {'name': 'Stoneware', 'parent': 'Ceramics'}, {'name': 'Earthenware', 'parent': 'Ceramics'},
	{'name': 'Textile'}, {'name': 'Weaving', 'parent': 'Textile'}, {'name': 'Carpet', 'parent': 'Textile'},
	{'name': 'Decorative art'}, {'name': 'Glass', 'parent': 'Decorative art'},
]

ROLES = ['Painter', 'Sculptor', 'Printmaker', 'Photographer', 'Ceramicist', 'Weaver', 'Metalworker', 'Glassmaker', 'Author', 'Workshop or guild']

COLLECTION_CATEGORIES = ['Thematic', 'Technique', 'Cross-cultural survey']

# -- Profiles: key => (title, nationality, born, died, collective, roles, labels, mediums, bio) --

P = {}


def person(key, title, nationality, born, died, roles, labels, mediums, bio):
	P[key] = dict(title=title, nationality=nationality, born=born, died=died, collective=False, roles=roles, labels=labels, mediums=mediums, bio=bio)


def group(key, title, nationality, roles, labels, mediums, bio, born=None, died=None):
	P[key] = dict(title=title, nationality=nationality, born=born, died=died, collective=True, roles=roles, labels=labels, mediums=mediums, bio=bio)


person('rembrandt', 'Rembrandt van Rijn', 'Dutch', 1606, 1669, ['Painter', 'Printmaker'], ['Dutch Golden Age', 'Baroque'], ['Oil'],
	'Painter and printmaker of Leiden and Amsterdam, celebrated for his command of light and for the psychological depth of his portraits.')
person('vermeer', 'Johannes Vermeer', 'Dutch', 1632, 1675, ['Painter'], ['Dutch Golden Age'], ['Oil'],
	'Painter of Delft who specialised in quiet domestic interiors. Fewer than forty paintings are attributed to him.')
person('gentileschi', 'Artemisia Gentileschi', 'Italian', 1593, 1654, ['Painter'], ['Baroque'], ['Oil'],
	'Baroque painter, trained by her father Orazio Gentileschi, and one of the most accomplished followers of Caravaggio. She was the first woman admitted to the Accademia delle Arti del Disegno in Florence.')
person('vigee-le-brun', 'Élisabeth Louise Vigée Le Brun', 'French', 1755, 1842, ['Painter'], ['Rococo', 'Portraiture'], ['Oil'],
	'Portrait painter who became a favourite of Marie Antoinette and worked for courts across Europe.')
person('morisot', 'Berthe Morisot', 'French', 1841, 1895, ['Painter', 'Printmaker'], ['Impressionism'], ['Oil'],
	'A founding member of the Impressionist group, she exhibited in seven of its eight exhibitions.')
person('bonheur', 'Rosa Bonheur', 'French', 1822, 1899, ['Painter', 'Sculptor'], ['Realism', 'Animal art'], ['Oil', 'Bronze'],
	'The leading animal painter of her day, who also modelled animals in clay and wax for casting in bronze.')
person('caravaggio', 'Caravaggio (Michelangelo Merisi)', 'Italian', 1571, 1610, ['Painter'], ['Baroque'], ['Oil'],
	'Painter whose dramatic use of light and shadow and unidealised figures changed European painting.')
person('de-la-tour', 'Georges de La Tour', 'French', 1593, 1652, ['Painter'], ['Baroque'], ['Oil'],
	'Painter from Lorraine known for night scenes lit by a single candle or torch.')
person('hals', 'Frans Hals', 'Dutch', 1582, 1666, ['Painter'], ['Dutch Golden Age', 'Portraiture'], ['Oil'],
	'Portraitist of Haarlem, admired for his quick, visible brushwork and lively sitters.')
person('chardin', 'Jean Siméon Chardin', 'French', 1699, 1779, ['Painter'], ['Rococo'], ['Oil'],
	'Painter of still lifes and quiet scenes of domestic life, working in a restrained style at the height of the Rococo.')
person('velazquez', 'Diego Velázquez', 'Spanish', 1599, 1660, ['Painter'], ['Baroque', 'Portraiture'], ['Oil'],
	'Court painter to Philip IV of Spain and the leading figure of the Spanish Golden Age of painting.')
person('poussin', 'Nicolas Poussin', 'French', 1594, 1665, ['Painter'], ['Baroque', 'Neoclassicism'], ['Oil'],
	'French painter who worked mostly in Rome, and whose ordered, classical compositions shaped later academic art.')
person('rubens', 'Peter Paul Rubens', 'Flemish', 1577, 1640, ['Painter'], ['Baroque'], ['Oil'],
	'Antwerp painter and diplomat, head of a large workshop, and the dominant figure of Flemish Baroque painting.')
person('cezanne', 'Paul Cézanne', 'French', 1839, 1906, ['Painter'], ['Post-Impressionism'], ['Oil'],
	'Painter whose structured approach to form and colour paved the way for Cubism.')
person('renoir', 'Auguste Renoir', 'French', 1841, 1919, ['Painter'], ['Impressionism'], ['Oil'],
	'Impressionist painter of figures, portraits and bathers, known for warm colour and soft brushwork.')
person('manet', 'Édouard Manet', 'French', 1832, 1883, ['Painter', 'Printmaker'], ['Realism', 'Impressionism'], ['Oil'],
	'Painter of modern Paris whose work bridged Realism and Impressionism.')
person('degas', 'Edgar Degas', 'French', 1834, 1917, ['Painter', 'Sculptor'], ['Impressionism'], ['Oil', 'Bronze'],
	'Painter, pastellist and sculptor of dancers, bathers and city life.')
person('fontana', 'Lavinia Fontana', 'Italian', 1552, 1614, ['Painter'], ['Renaissance', 'Portraiture'], ['Oil'],
	'Painter of Bologna and Rome, regarded as one of the first women in Europe to work as a professional painter outside a convent.')
person('sargent', 'John Singer Sargent', 'American', 1856, 1925, ['Painter'], ['Portraiture'], ['Oil'],
	'American portrait painter born in Florence and active in Paris, London and the United States.')
person('homer', 'Winslow Homer', 'American', 1836, 1910, ['Painter', 'Printmaker'], ['Realism'], ['Oil'],
	'American painter and illustrator best known for his seascapes and scenes of everyday life.')
person('cameron', 'Julia Margaret Cameron', 'British', 1815, 1879, ['Photographer'], ['Early photography'], ['Albumen print'],
	'Born in Calcutta, she took up photography in her late forties and made soft-focus portraits and allegorical scenes.')
person('muybridge', 'Eadweard Muybridge', 'British and American', 1830, 1904, ['Photographer'], ['Early photography'], ['Albumen print'],
	'Photographer who pioneered the use of multiple cameras to capture the stages of animal and human motion.')
person('emerson', 'Peter Henry Emerson', 'British', 1856, 1936, ['Photographer'], ['Pictorialism'], ['Platinum print'],
	'Photographer and writer who argued for a naturalistic approach to photography, and made his best-known work in rural Norfolk.')
person('hokusai', 'Katsushika Hokusai', 'Japanese', 1760, 1849, ['Printmaker', 'Painter'], ['Ukiyo-e'], ['Woodblock'],
	'Edo-period painter and printmaker of landscapes, nature and everyday life.')
person('hiroshige', 'Utagawa Hiroshige', 'Japanese', 1797, 1858, ['Printmaker'], ['Ukiyo-e'], ['Woodblock'],
	'Edo-period printmaker best known for landscape series, including views of Edo and the Tōkaidō road.')
person('utamaro', 'Kitagawa Utamaro', 'Japanese', 1754, 1806, ['Printmaker'], ['Ukiyo-e'], ['Woodblock'],
	'Printmaker famed for his portraits of women, bijin-ga, in the Edo pleasure quarters.')
person('kuniyoshi', 'Utagawa Kuniyoshi', 'Japanese', 1797, 1861, ['Printmaker'], ['Ukiyo-e'], ['Woodblock'],
	'Printmaker of warriors, legends, cats and landscapes in the late Edo period.')
person('tang-yin', 'Tang Yin', 'Chinese', 1470, 1524, ['Painter'], ['Ming dynasty'], ['Ink and watercolour'],
	'Ming-dynasty painter, poet and calligrapher of Suzhou, one of the Four Masters of the Ming.')
person('nidha-mal', 'Nidha Mal', 'Indian', None, None, ['Painter'], ['Mughal'], ['Ink and watercolour'],
	'Court painter active in India around the middle of the 18th century.')
person('mir-kalan-khan', 'Mir Kalan Khan', 'Indian', None, None, ['Painter'], ['Mughal'], ['Ink and watercolour'],
	'Painter active in India in the second half of the 18th century.')
person('firdausi', "Abu'l Qasim Firdausi", 'Persian', 935, 1020, ['Author'], ['Persian'], ['Manuscript illumination'],
	'Poet of the Shahnama, the Persian Book of Kings, whose verses have been illustrated by painters for a thousand years.')
person('gauguin', 'Paul Gauguin', 'French', 1848, 1903, ['Painter', 'Ceramicist'], ['Post-Impressionism'], ['Stoneware'],
	'Painter who also worked in ceramics, wood and print, and who spent his last years in French Polynesia.')

group('tiffany-studios', 'Tiffany Studios', 'American', ['Glassmaker', 'Metalworker'], ['Art Nouveau'], ['Glass'],
	'The New York workshop of Louis Comfort Tiffany, known for leaded Favrile glass lamps, windows and mosaics.', 1902, 1932)
group('igun-eronmwon', "Igun Eronmwon (Benin brass-casters' guild)", 'Edo peoples (Benin)', ['Metalworker', 'Workshop or guild'], ['Benin'], ['Brass'],
	"The guild of brass-casters in the Kingdom of Benin, which cast plaques, heads and other works for the royal palace using the lost-wax method.")
group('yoruba-artists', 'Yoruba artists', 'Yoruba peoples', ['Sculptor'], ['Yoruba'], ['Stone'],
	'Carvers and casters of the Yoruba-speaking city-states of what is now south-western Nigeria.')
group('dogon-blacksmiths', 'Dogon blacksmiths', 'Dogon peoples', ['Sculptor', 'Metalworker'], ['Dogon'], ['Wood'],
	'In Dogon society, blacksmiths are the carvers of wooden figures and masks as well as makers of iron tools.')
group('asante-weavers', 'Asante-Akan weavers', 'Asante-Akan peoples', ['Weaver'], ['Asante'], ['Weaving'],
	'Weavers of the Asante and Akan peoples of present-day Ghana, who weave narrow strips of cotton and silk that are sewn together as kente.')
group('moche-artists', 'Moche artists', 'Moche', ['Ceramicist'], ['Moche'], ['Earthenware'],
	'Potters of the Moche culture of the north coast of Peru, who modelled and painted vessels with portraits, animals and ritual scenes.')
group('maya-artists', 'Maya artists', 'Maya', ['Ceramicist'], ['Maya'], ['Earthenware'],
	'Painters and potters of the Maya civilisation of Mesoamerica.')
group('navajo-weavers', 'Diné (Navajo) weavers', 'Diné/Navajo', ['Weaver'], ['Diné (Navajo)'], ['Weaving'],
	'Weavers of the Diné of the American Southwest, whose blankets were traded widely in the 19th century.')
group('maori-weavers', 'Māori weavers', 'Māori', ['Weaver'], ['Māori'], ['Weaving'],
	'Weavers of Aotearoa New Zealand, whose cloaks (korowai and kaitaka) are woven by hand from harakeke, or flax.')
group('goryeo-potters', 'Goryeo potters', 'Korean', ['Ceramicist'], ['Goryeo'], ['Stoneware'],
	'Potters of the Goryeo dynasty (918–1392), famous for celadon wares with carved and inlaid decoration.')
group('egyptian-bronze-casters', 'Late Period Egyptian bronze-casters', 'Egyptian', ['Metalworker'], ['Ancient Egyptian'], ['Bronze'],
	'Anonymous workshops that cast votive bronzes of gods and sacred animals in the first millennium BCE.')
group('deccan-painters', 'Deccan court painters', 'Indian', ['Painter'], ['Deccan'], ['Manuscript illumination'],
	'Anonymous painters of the Deccan courts of central India.')
group('mughal-court-painters', 'Mughal court painters', 'Indian', ['Painter'], ['Mughal'], ['Ink and watercolour'],
	'The anonymous painters of the Mughal court workshops in northern India.')
group('persian-carpet-weavers', 'Persian carpet weavers', 'Iranian', ['Weaver'], ['Persian'], ['Carpet'],
	'Anonymous weavers of the workshops of Safavid Iran.')

# -- Works: (met id, key, profile, labels, mediums, description, related) --
#   Descriptions are editorial; every factual field comes from the Met record.

W = [
	(437394, 'aristotle-with-a-bust-of-homer', 'rembrandt', ['Dutch Golden Age', 'Baroque'], ['Oil'],
		'The philosopher rests a hand on the bust of the poet, a gold chain across his chest. Painted for the Sicilian collector Antonio Ruffo.', ['young-woman-with-a-water-pitcher']),
	(437881, 'young-woman-with-a-water-pitcher', 'vermeer', ['Dutch Golden Age'], ['Oil'],
		'A woman opens a window and rests her hand on a metal pitcher in a room lit from the left. One of five paintings by Vermeer at the Met.', ['aristotle-with-a-bust-of-homer']),
	(436453, 'esther-before-ahasuerus', 'gentileschi', ['Baroque'], ['Oil'],
		'Esther, overcome, faints as she approaches King Ahasuerus to plead for her people.', ['the-musicians']),
	(437898, 'madame-grand', 'vigee-le-brun', ['Rococo', 'Portraiture'], ['Oil'],
		'A portrait of Noël Catherine Vorlée, later Madame Grand, by the artist who painted Marie Antoinette.', ['madame-x']),
	(438009, 'the-pink-dress', 'morisot', ['Impressionism', 'Portraiture'], ['Oil'],
		'A young sitter in a pink dress, painted in the loose, feathery brushwork that marks Morisot out among the Impressionists.', []),
	(435702, 'the-horse-fair', 'bonheur', ['Realism', 'Animal art'], ['Oil'],
		'A frieze of draught horses in motion at a Paris horse market, painted on a canvas some sixteen feet long.', ['walking-bull']),
	(201411, 'walking-bull', 'bonheur', ['Realism', 'Animal art'], ['Bronze'],
		'A small bronze of a bull mid-stride: Bonheur is better known for her paintings, but she also modelled animals for casting.', ['the-horse-fair']),
	(435844, 'the-musicians', 'caravaggio', ['Baroque'], ['Oil'],
		'Young musicians tune their instruments in a closely cropped, richly lit scene painted for Cardinal Francesco Maria del Monte.', ['the-penitent-magdalen']),
	(436839, 'the-penitent-magdalen', 'de-la-tour', ['Baroque'], ['Oil'],
		'Mary Magdalen meditates by candlelight, the single flame setting the stillness of the scene.', ['the-musicians']),
	(436618, 'anna-van-der-aar', 'hals', ['Dutch Golden Age', 'Portraiture'], ['Oil'],
		'A small, lively panel portrait of the wife of the scholar Petrus Scriverius, painted as a companion to his portrait.', []),
	(435888, 'soap-bubbles', 'chardin', ['Rococo'], ['Oil'],
		'A young man leans from a ledge to blow a bubble, a familiar emblem of the brevity of life. Chardin painted several versions.', []),
	(437872, 'mariana-of-austria', 'velazquez', ['Baroque', 'Portraiture'], ['Oil'],
		'Mariana of Austria, second wife of Philip IV and queen of Spain.', ['madame-x']),
	(437329, 'the-abduction-of-the-sabine-women', 'poussin', ['Baroque', 'Neoclassicism'], ['Oil'],
		'The legendary seizure of the Sabine women by the early Romans, staged as a tightly ordered frieze of figures.', []),
	(437532, 'rubens-helena-fourment-and-their-son', 'rubens', ['Baroque', 'Portraiture'], ['Oil'],
		'The painter with his second wife Helena Fourment and their son, a rare family portrait.', []),
	(435875, 'madame-cezanne-in-the-conservatory', 'cezanne', ['Post-Impressionism', 'Portraiture'], ['Oil'],
		"One of Cézanne's many portraits of his wife, Hortense Fiquet.", []),
	(459110, 'young-girl-bathing', 'renoir', ['Impressionism'], ['Oil'],
		'A bather at the water’s edge, painted in 1892 in Renoir’s later, more sculptural manner.', []),
	(436957, 'madame-edouard-manet', 'manet', ['Realism', 'Portraiture'], ['Oil'],
		"Manet's wife, Suzanne Leenhoff, a Dutch-born pianist.", ['madame-cezanne-in-the-conservatory']),
	(196442, 'study-in-the-nude-for-the-little-fourteen-year-old-dancer', 'degas', ['Impressionism'], ['Bronze'],
		"A preparatory nude study for the only sculpture Degas showed in his lifetime. The bronzes were cast after his death.", []),
	(436310, 'portrait-of-a-prelate', 'fontana', ['Renaissance', 'Portraiture'], ['Oil'],
		'A small, precise portrait on copper by one of the first women to work as a professional painter in Europe.', []),
	(12127, 'madame-x', 'sargent', ['Portraiture'], ['Oil'],
		'The society beauty Virginie Gautreau, whose plunging black gown caused a scandal when the portrait was shown in Paris in 1884.', ['madame-grand', 'mariana-of-austria']),
	(11122, 'the-gulf-stream', 'homer', ['Realism'], ['Oil'],
		'A sailor adrift on a battered boat among sharks, with a waterspout on the horizon; Homer reworked it after it was criticised.', ['under-the-wave-off-kanagawa']),
	(282118, 'vivien-and-merlin', 'cameron', ['Early photography'], ['Albumen print'],
		"A staged scene from Tennyson's Idylls of the King, made with friends and household members as models.", ['gathering-water-lilies']),
	(700109, 'attitudes-of-animals-in-motion', 'muybridge', ['Early photography', 'Animal art'], ['Albumen print'],
		"A plate from Muybridge's motion studies, in which a row of cameras captured the stages of an animal's movement.", ['the-horse-fair']),
	(286671, 'gathering-water-lilies', 'emerson', ['Pictorialism'], ['Platinum print'],
		'A naturalistic photograph of rural life on the Norfolk Broads, made with the soft focus Emerson championed.', ['vivien-and-merlin']),
	(39799, 'under-the-wave-off-kanagawa', 'hokusai', ['Ukiyo-e'], ['Woodblock'],
		'Known as The Great Wave, from the series Thirty-six Views of Mount Fuji.', ['sudden-shower-over-shin-ohashi-bridge']),
	(55433, 'sudden-shower-over-shin-ohashi-bridge', 'hiroshige', ['Ukiyo-e'], ['Woodblock'],
		'Pedestrians hurry across a bridge in a downpour. Van Gogh made an oil copy of this design.', ['under-the-wave-off-kanagawa']),
	(36623, 'woman-relaxing-after-her-bath', 'utamaro', ['Ukiyo-e', 'Portraiture'], ['Woodblock'],
		'A bijin-ga, or portrait of a beauty, by the printmaker who made the genre his own.', []),
	(51084, 'ochanomizu-in-the-rain', 'kuniyoshi', ['Ukiyo-e'], ['Woodblock'],
		'A rainy-day view of Ochanomizu in Edo, now Tokyo.', ['sudden-shower-over-shin-ohashi-bridge']),
	(44573, 'bamboo-in-a-spring-thunderstorm', 'tang-yin', ['Ming dynasty'], ['Ink and watercolour'],
		'Bamboo bending in a storm, brushed in ink on a folding fan later mounted as an album leaf.', []),
	(42278, 'maebyeong-with-lotus-decoration', 'goryeo-potters', ['Goryeo'], ['Stoneware'],
		'A maebyeong, the narrow-necked vase form made for plum branches, with carved and incised lotus under a celadon glaze.', []),
	(454049, 'prince-and-ladies-in-a-garden', 'nidha-mal', ['Mughal'], ['Ink and watercolour'],
		'A prince and his ladies at leisure in a garden, in ink, opaque watercolour and gold.', ['a-gathering-of-holy-men-of-different-faiths']),
	(456967, 'a-gathering-of-holy-men-of-different-faiths', 'mir-kalan-khan', ['Mughal'], ['Ink and watercolour'],
		'Holy men of different faiths gathered in one meditative scene.', ['prince-and-ladies-in-a-garden']),
	(452418, 'solanki-raga', 'deccan-painters', ['Deccan'], ['Manuscript illumination'],
		'A folio from a Ragamala, a set of paintings that picture musical modes as scenes and characters.', ['bahram-gur-hunting-with-azada']),
	(825637, 'durbar-of-emperor-akbar-shah-ii', 'mughal-court-painters', ['Mughal'], ['Ink and watercolour'],
		'A court scene (durbar) of Akbar Shah II, the penultimate Mughal emperor.', ['prince-and-ladies-in-a-garden']),
	(451411, 'bahram-gur-hunting-with-azada', 'firdausi', ['Persian'], ['Manuscript illumination'],
		"The Sasanian king Bahram Gur hunts with his companion Azada, an episode from Firdausi's Shahnama, the Persian Book of Kings.", ['solanki-raga']),
	(450716, 'the-anhalt-medallion-carpet', 'persian-carpet-weavers', ['Persian'], ['Carpet'],
		'A large medallion carpet in cotton, silk and wool, knotted in Iran in the 16th century.', []),
	(316393, 'plaque-with-warrior-and-attendants', 'igun-eronmwon', ['Benin'], ['Brass'],
		'A cast brass plaque of the type that once lined the royal palace of Benin, showing a warrior with his attendants.', ['head-from-a-figure']),
	(312519, 'head-from-a-figure', 'yoruba-artists', ['Yoruba'], ['Stone'],
		'A soapstone head from a Yoruba figure, carved between the 12th and 15th centuries.', ['plaque-with-warrior-and-attendants']),
	(312289, 'dogon-equestrian', 'dogon-blacksmiths', ['Dogon', 'Animal art'], ['Wood'],
		'A rider on horseback in wood and metal, a recurring figure in Dogon sculpture.', ['priest-with-raised-arms']),
	(310765, 'priest-with-raised-arms', 'dogon-blacksmiths', ['Dogon'], ['Wood'],
		'A tall carved figure of a priest with raised arms.', ['dogon-equestrian']),
	(85576, 'mans-kente-prestige-cloth', 'asante-weavers', ['Asante'], ['Weaving'],
		'Narrow strips of cotton and silk woven on a loom and sewn together into a prestige cloth.', ['chiefs-blanket']),
	(318602, 'vessel-with-face-neck', 'moche-artists', ['Moche', 'Portraiture'], ['Earthenware'],
		'A ceramic vessel whose neck is modelled as a face, from the north coast of Peru.', ['vessel-with-mythological-scene']),
	(662967, 'vessel-with-mythological-scene', 'maya-artists', ['Maya'], ['Earthenware'],
		'A Maya ceramic vessel painted with a mythological scene.', ['vessel-with-face-neck']),
	(823661, 'chiefs-blanket', 'navajo-weavers', ['Diné (Navajo)'], ['Weaving'],
		"A woollen \"chief's blanket\", woven by Diné weavers and traded widely across the plains and plateau.", ['mans-kente-prestige-cloth']),
	(839896, 'kaitaka-aronui', 'maori-weavers', ['Māori'], ['Weaving'],
		'A fine cloak woven from harakeke (flax) with wool, worn on formal occasions.', ['chiefs-blanket']),
	(571798, 'cat-statuette', 'egyptian-bronze-casters', ['Ancient Egyptian', 'Animal art'], ['Bronze'],
		'A cast bronze cat of the Late Period; cats were sacred to the goddess Bastet.', ['walking-bull']),
	(20633, 'table-lamp', 'tiffany-studios', ['Art Nouveau'], ['Glass'],
		'A lamp of leaded Favrile glass on a patinated bronze base.', []),
	(239364, 'vessel-with-women-and-goats', 'gauguin', ['Post-Impressionism'], ['Stoneware'],
		'A stoneware vessel by the painter, who took ceramics as seriously as his canvases.', []),
]

# -- Collections: (key, title, category, standfirst, description, work keys, profile keys) --

C = [
	('women-who-shaped-the-canon', 'Women Who Shaped the Canon', 'Thematic',
		'Painters and photographers who made their name against the odds.',
		"A selection of women who worked at the highest level in their time: court portraitists, Baroque history painters, Impressionists and pioneers of photography.",
		['esther-before-ahasuerus', 'madame-grand', 'the-pink-dress', 'the-horse-fair', 'walking-bull', 'portrait-of-a-prelate', 'vivien-and-merlin'],
		['gentileschi', 'vigee-le-brun', 'morisot', 'bonheur', 'fontana', 'cameron']),
	('weather-and-water', 'Weather and Water', 'Thematic',
		'Rain, waves, storms and still ponds, from Edo to Norfolk.',
		'How different traditions picture water and weather: the printmakers of Edo, a Ming master of ink, and a sailor adrift in the Gulf Stream.',
		['under-the-wave-off-kanagawa', 'sudden-shower-over-shin-ohashi-bridge', 'ochanomizu-in-the-rain', 'bamboo-in-a-spring-thunderstorm', 'the-gulf-stream', 'gathering-water-lilies', 'young-girl-bathing'],
		['hokusai', 'hiroshige', 'kuniyoshi', 'tang-yin', 'homer', 'emerson', 'renoir']),
	('faces-across-cultures', 'Faces Across Cultures', 'Cross-cultural survey',
		'The portrait as a universal act, from Moche potters to Parisian salons.',
		'Portraits in oil, stone, clay and brass from five continents, brought together to compare what different cultures choose to show of a face.',
		['madame-grand', 'madame-x', 'mariana-of-austria', 'madame-edouard-manet', 'anna-van-der-aar', 'rubens-helena-fourment-and-their-son', 'madame-cezanne-in-the-conservatory', 'head-from-a-figure', 'vessel-with-face-neck', 'plaque-with-warrior-and-attendants', 'woman-relaxing-after-her-bath'],
		['vigee-le-brun', 'sargent', 'velazquez', 'manet', 'hals', 'rubens', 'cezanne', 'yoruba-artists', 'moche-artists', 'igun-eronmwon', 'utamaro']),
	('made-by-hand', 'Made by Hand', 'Technique',
		'Clay, cloth, glass and metal: the work of skilled hands.',
		'Objects whose beauty lies in the way they were made: celadon glaze, woven strips of silk, lost-wax brass, leaded glass and hand-knotted wool.',
		['maebyeong-with-lotus-decoration', 'vessel-with-face-neck', 'vessel-with-mythological-scene', 'mans-kente-prestige-cloth', 'chiefs-blanket', 'kaitaka-aronui', 'plaque-with-warrior-and-attendants', 'table-lamp', 'the-anhalt-medallion-carpet', 'vessel-with-women-and-goats'],
		['goryeo-potters', 'moche-artists', 'maya-artists', 'asante-weavers', 'navajo-weavers', 'maori-weavers', 'igun-eronmwon', 'tiffany-studios', 'persian-carpet-weavers', 'gauguin']),
	('the-painted-page', 'The Painted Page', 'Technique',
		'Manuscripts and miniatures from Persia and India.',
		"Small paintings made for books and albums: a folio from the Shahnama, a Ragamala page and the intimate courtly scenes of 18th-century India.",
		['bahram-gur-hunting-with-azada', 'solanki-raga', 'prince-and-ladies-in-a-garden', 'a-gathering-of-holy-men-of-different-faiths', 'durbar-of-emperor-akbar-shah-ii'],
		['firdausi', 'deccan-painters', 'nidha-mal', 'mir-kalan-khan', 'mughal-court-painters']),
	('light-in-the-dark', 'Light in the Dark', 'Thematic',
		'Candlelight, shadow and the Baroque art of chiaroscuro.',
		'Painters who built their pictures around a pool of light: Caravaggio and his followers, a Lorraine master of the candle, and the Dutch interior.',
		['the-musicians', 'the-penitent-magdalen', 'esther-before-ahasuerus', 'aristotle-with-a-bust-of-homer', 'young-woman-with-a-water-pitcher'],
		['caravaggio', 'de-la-tour', 'gentileschi', 'rembrandt', 'vermeer']),
	('stories-on-canvas', 'Stories on Canvas', 'Thematic',
		'Myth, scripture and a quiet moment at sea.',
		'Paintings that tell a story or hold a single charged moment: a legendary abduction, a queen who faints, a sailor adrift and a boy with a soap bubble.',
		['the-abduction-of-the-sabine-women', 'esther-before-ahasuerus', 'the-gulf-stream', 'soap-bubbles', 'the-horse-fair'],
		['poussin', 'gentileschi', 'homer', 'chardin', 'bonheur']),
	('animals-observed', 'Animals Observed', 'Cross-cultural survey',
		'A horse fair, a sacred cat and a galloping rider.',
		'How artists in different times and places have looked at animals, from sacred Egyptian bronzes and Dogon riders to the scientific eye of Muybridge.',
		['the-horse-fair', 'walking-bull', 'attitudes-of-animals-in-motion', 'cat-statuette', 'dogon-equestrian'],
		['bonheur', 'muybridge', 'egyptian-bronze-casters', 'dogon-blacksmiths']),
	('beyond-the-canvas', 'Beyond the Canvas', 'Technique',
		'Sculptors, potters and weavers working outside the painted surface.',
		'Painters who modelled in bronze or clay, and sculptors who cast, carved and joined: three dimensions, from a Degas dancer to a Benin plaque.',
		['study-in-the-nude-for-the-little-fourteen-year-old-dancer', 'walking-bull', 'vessel-with-women-and-goats', 'head-from-a-figure', 'dogon-equestrian', 'priest-with-raised-arms', 'cat-statuette'],
		['degas', 'bonheur', 'gauguin', 'yoruba-artists', 'dogon-blacksmiths', 'egyptian-bronze-casters']),
]


def fetch(met_id):
	for attempt in range(4):
		try:
			req = urllib.request.Request(API % met_id, headers={'User-Agent': UA})
			with urllib.request.urlopen(req, timeout=30) as res:
				return json.load(res)
		except Exception:  # noqa: BLE001
			time.sleep(2)
	raise SystemExit(f'Could not fetch Met object {met_id}')


# Numeric years are given only where the Met's date states them. Vague or BCE
# dates ("mid-19th century", "664–525 BCE") stay as words, which the year
# fields can't hold. key => (year, year to, show the Met's wording as well).
DATES = {
	'aristotle-with-a-bust-of-homer': (1653, None, False),
	'young-woman-with-a-water-pitcher': (1662, None, True),
	'esther-before-ahasuerus': (1620, 1629, True),
	'madame-grand': (1783, None, False),
	'the-pink-dress': (1870, None, True),
	'the-horse-fair': (1852, 1855, False),
	'walking-bull': (1846, None, True),
	'the-musicians': (1597, None, False),
	'the-penitent-magdalen': (1640, None, True),
	'anna-van-der-aar': (1626, None, False),
	'soap-bubbles': (1733, 1734, True),
	'mariana-of-austria': (None, None, False),
	'the-abduction-of-the-sabine-women': (1633, 1634, True),
	'rubens-helena-fourment-and-their-son': (1635, None, True),
	'madame-cezanne-in-the-conservatory': (1891, None, False),
	'young-girl-bathing': (1892, None, False),
	'madame-edouard-manet': (1873, None, True),
	'study-in-the-nude-for-the-little-fourteen-year-old-dancer': (1878, 1880, True),
	'portrait-of-a-prelate': (1580, None, True),
	'madame-x': (1883, 1884, False),
	'the-gulf-stream': (1899, None, True),
	'vivien-and-merlin': (1874, None, False),
	'attitudes-of-animals-in-motion': (1879, None, True),
	'gathering-water-lilies': (1886, None, False),
	'under-the-wave-off-kanagawa': (1830, 1832, True),
	'sudden-shower-over-shin-ohashi-bridge': (1857, None, False),
	'woman-relaxing-after-her-bath': (1790, 1799, True),
	'ochanomizu-in-the-rain': (None, None, True),
	'bamboo-in-a-spring-thunderstorm': (None, None, True),
	'maebyeong-with-lotus-decoration': (None, None, True),
	'prince-and-ladies-in-a-garden': (None, None, True),
	'a-gathering-of-holy-men-of-different-faiths': (1770, 1775, True),
	'solanki-raga': (1590, None, True),
	'durbar-of-emperor-akbar-shah-ii': (1820, 1830, False),
	'bahram-gur-hunting-with-azada': (1352, None, True),
	'the-anhalt-medallion-carpet': (None, None, True),
	'plaque-with-warrior-and-attendants': (1540, 1570, True),
	'head-from-a-figure': (None, None, True),
	'dogon-equestrian': (None, None, True),
	'priest-with-raised-arms': (None, None, True),
	'mans-kente-prestige-cloth': (None, None, True),
	'vessel-with-face-neck': (200, 800, True),
	'vessel-with-mythological-scene': (600, 800, True),
	'chiefs-blanket': (1865, None, True),
	'kaitaka-aronui': (None, None, True),
	'cat-statuette': (None, None, True),
	'table-lamp': (1895, 1898, True),
	'vessel-with-women-and-goats': (1887, 1889, True),
}


def dates_of(key, record):
	"""The work's date fields: year, year_to and date_text, where they apply."""
	year, year_to, words = DATES[key]
	fields = {}
	if year:
		fields['year'] = year
	if year_to:
		fields['year_to'] = year_to
	if words and record['objectDate'].strip():
		fields['date_text'] = record['objectDate'].strip().replace('modeled', 'modelled')
	return fields


def folder_of(profile):
	"""The Media Library folder for a work's image: a folder per named artist
	under "artworks", and one shared folder for peoples, guilds and anonymous
	workshops, so there aren't a dozen folders holding a single file."""
	named = not P[profile]['collective'] or profile == 'tiffany-studios'
	return ['artworks', profile if named else 'collective-and-anonymous']


def build():
	profiles = []
	for key, p in P.items():
		fields = {
			'nationality': p['nationality'], 'show_nationality': True, 'is_collective': p['collective'],
			'bio': f'<p>{p["bio"]}</p>',
			'notes': '<p>Written for the Galerio demo. Dates and nationality follow the Metropolitan Museum of Art Open Access records.</p>',
		}
		if p['born'] is not None:
			fields['year_of_birth'] = p['born']
			fields['year_of_death'] = p['died']
			fields['show_lifespan'] = True
		profiles.append({
			'key': key, 'title': p['title'], 'fields': fields,
			'terms': {'galerio_role': p['roles'], 'galerio_label': p['labels'], 'galerio_medium': p['mediums']},
		})

	works = []
	for met_id, key, profile, labels, mediums, description, related in W:
		o = fetch(met_id)
		assert o['isPublicDomain'] and o['primaryImage'], met_id
		credit = f'{MET}. {o["creditLine"]}. Accession number {o["accessionNumber"]}. Open Access, CC0 1.0.'
		works.append({
			'key': key, 'title': o['title'], 'creator': profile, 'related': related, 'folder': folder_of(profile),
			'fields': {
				**dates_of(key, o), 'medium': o['medium'], 'dimensions': o['dimensions'].replace('\r\n', '; ').replace('\n', '; '),
				'location': MET,
				'description': f'<p>{description}</p><p><small>Source: {o["objectDate"]}; accession number {o["accessionNumber"]}, {MET}. {o["creditLine"]}. Image: public domain (CC0 1.0), Met Open Access.</small></p>',
			},
			'terms': {'galerio_label': labels, 'galerio_medium': mediums},
			'source': {'url': o['objectURL'], 'label': 'Metropolitan Museum of Art record'},
			'image': {
				'url': o['primaryImage'], 'fallback': [o['primaryImageSmall']] if o['primaryImageSmall'] else [],
				'page': o['objectURL'], 'licence': 'CC0 1.0 (Met Open Access)', 'credit': credit, 'alt': o['title'],
			},
		})
		time.sleep(0.15)

	collections = [{
		'key': key, 'title': title, 'works': works_, 'profiles': profiles_,
		'fields': {'standfirst': standfirst, 'description': f'<p>{description}</p>',
			'notes': '<p>A conceptual collection written for the Galerio demo, not a real-world exhibition. Works are from the Metropolitan Museum of Art Open Access collection.</p>'},
		'terms': {'galerio_collection_category': [category]},
	} for key, title, category, standfirst, description, works_, profiles_ in C]

	return {
		'meta': {
			'about': 'Demo content for galerio.net: public-domain masterworks from the Metropolitan Museum of Art Open Access collection, '
				'chosen to show the range of what a Galerio site can hold.',
			'source': 'https://metmuseum.github.io/', 'licence': 'CC0 1.0 (Met Open Access)',
		},
		'terms': {
			'galerio_label': LABELS, 'galerio_medium': MEDIUMS, 'galerio_role': ROLES,
			'galerio_collection_category': COLLECTION_CATEGORIES,
		},
		'profiles': profiles, 'works': works, 'collections': collections,
	}


if __name__ == '__main__':
	manifest = build()
	keys = {w['key'] for w in manifest['works']}
	for c in manifest['collections']:
		missing = [k for k in c['works'] if k not in keys]
		assert not missing, (c['key'], missing)
	for w in manifest['works']:
		assert w['creator'] in P, w['key']
		assert all(r in keys for r in w['related']), (w['key'], w['related'])
	with open(os.path.join(HERE, 'demo-masters.json'), 'w') as handle:
		json.dump(manifest, handle, indent='\t', ensure_ascii=False)
		handle.write('\n')
	print(f'{len(manifest["works"])} works, {len(manifest["profiles"])} profiles, {len(manifest["collections"])} collections')
