const models: SmartPlaylistModel[] = [
  {
    name: 'title',
    type: 'text',
    label: 'Title',
  },
  {
    name: 'album.name',
    type: 'text',
    label: 'Album',
  },
  {
    name: 'artist.name',
    type: 'text',
    label: 'Artist',
  },
  {
    name: 'genre',
    type: 'text',
    label: 'Genre',
  },
  {
    name: 'year',
    type: 'number',
    label: 'Year',
  },
  {
    name: 'bpm',
    type: 'number',
    label: 'BPM',
  },
  {
    name: 'musical_key',
    type: 'text',
    label: 'Key',
  },
  {
    name: 'interactions.play_count',
    type: 'number',
    label: 'Play Count',
  },
  {
    name: 'interactions.last_played_at',
    type: 'date',
    label: 'Last Played',
  },
  {
    name: 'length',
    type: 'number',
    label: 'Length',
    unit: 'seconds',
  },
  {
    name: 'created_at',
    type: 'date',
    label: 'Date Added',
  },
  {
    name: 'file_created_at',
    type: 'date',
    label: 'File Created',
  },
  {
    name: 'updated_at',
    type: 'date',
    label: 'Date Modified',
  },
]

export default models
